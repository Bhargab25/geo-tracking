import React, { useEffect, useState } from 'react';
import { ApolloClient, InMemoryCache, gql, HttpLink } from '@apollo/client';
import { ApolloProvider, useQuery } from '@apollo/client/react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Initialize Apollo Client pointing directly to our Laravel backend container port
const client = new ApolloClient({
  link: new HttpLink({ uri: 'http://localhost:8000/graphql' }),
  cache: new InMemoryCache(),
});

// The single, deeply nested query replacing multiple REST API requests
const GET_FLEET_LOGISTICS = gql`
  query GetFleetLogistics($fleetId: ID!) {
    fleet(id: $fleetId) {
      id
      name
      status
      geofences {
        id
        zone_name
        risk_level
        coordinates
      }
      shipments {
        id
        tracking_number
        status
        latitude
        longitude
      }
    }
  }
`;

function CommandCenterMap() {
  const [mapInstance, setMapInstance] = useState(null);
  const [layerGroup] = useState(L.layerGroup());

  // Pull operational logistics data and poll every 2000ms for live position shifts
  const { loading, error, data } = useQuery(GET_FLEET_LOGISTICS, {
    variables: { fleetId: "1" },
    pollInterval: 2000, 
  });

  // 1. Initialize core Map Canvas once on mount
  useEffect(() => {
    const map = L.map('map-container').setView([51.505, -0.12], 12);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    layerGroup.addTo(map);
    setMapInstance(map);

    return () => map.remove();
  }, []);

  // 2. Redraw map markers and polygons whenever the GraphQL polling data changes
  useEffect(() => {
    if (!mapInstance || !data || !data.fleet) return;

    // Clear previous visual vectors to avoid ghost duplicate trails
    layerGroup.clearLayers();

    // Render Geofence Polygons
    data.fleet.geofences.forEach(geofence => {
      const isHighRisk = geofence.risk_level === 'high';
      L.polygon(geofence.coordinates, {
        color: isHighRisk ? '#ef4444' : '#3b82f6',
        fillColor: isHighRisk ? '#f87171' : '#93c5fd',
        fillOpacity: 0.35,
        weight: 2
      })
      .bindPopup(`<b>Geofence Boundary:</b> ${geofence.zone_name}<br/>Risk: ${geofence.risk_level.toUpperCase()}`)
      .addTo(layerGroup);
    });

    // Render Live Shipment Location Markers
    data.fleet.shipments.forEach(shipment => {
      if (!shipment.latitude || !shipment.longitude) return;

      const isBreached = shipment.status === 'breached';
      
      // Construct custom colored marker dot
      const markerHtml = `
        <div style="
          background-color: ${isBreached ? '#dc2626' : '#10b981'};
          width: 14px; height: 14px; border-radius: 50%;
          border: 2px solid white; box-shadow: 0 0 8px rgba(0,0,0,0.4);
          animation: ${isBreached ? 'pulse 1s infinite alternate' : 'none'};
        "></div>
      `;

      const customIcon = L.divIcon({
        html: markerHtml,
        className: 'custom-gps-marker',
        iconSize: [14, 14]
      });

      L.marker([shipment.latitude, shipment.longitude], { icon: customIcon })
        .bindPopup(`
          <b>Asset:</b> ${shipment.tracking_number}<br/>
          <b>Status:</b> <span style="color: ${isBreached ? 'red' : 'green'}">${shipment.status.toUpperCase()}</span>
        `)
        .addTo(layerGroup);
    });

  }, [data, mapInstance]);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100vh', fontFamily: 'sans-serif' }}>
      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
        @keyframes pulse {
          from { transform: scale(0.9); opacity: 0.8; }
          to { transform: scale(1.1); opacity: 1; }
        }
      `}</style>
      <header style={{ padding: '16px', background: '#1e293b', color: 'white' }}>
        <h2 style={{ margin: 0 }}>🗺️ Operational Control Room: {data?.fleet?.name || 'Connecting...'}</h2>
        <small>
          {data?.fleet?.status 
            ? `System Status: ${data.fleet.status.toUpperCase()} | Polling Frequency: 2Hz`
            : 'Fetching system updates...'}
        </small>
      </header>
      <div style={{ flex: 1, width: '100%', position: 'relative' }}>
        <div id="map-container" style={{ width: '100%', height: '100%' }}></div>
        {(loading && !data) && (
          <div className="status-banner" style={{
            position: 'absolute', top: 0, left: 0, right: 0, bottom: 0,
            background: 'rgba(30, 41, 59, 0.85)', color: '#f8fafc', zIndex: 1000,
            display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
            fontSize: '18px', gap: '12px'
          }}>
            <div className="spinner" style={{
              width: '32px', height: '32px', border: '4px solid #38bdf8',
              borderTopColor: 'transparent', borderRadius: '50%', animation: 'spin 1s linear infinite'
            }}></div>
            Loading Fleet Tracking Framework...
          </div>
        )}
        {error && (
          <div className="status-banner error" style={{
            position: 'absolute', top: 0, left: 0, right: 0, bottom: 0,
            background: 'rgba(239, 68, 68, 0.95)', color: '#ffffff', zIndex: 1000,
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: '18px', padding: '24px', textAlign: 'center'
          }}>
            GraphQL Bridge Failure: {error.message}
          </div>
        )}
      </div>
    </div>
  );
}

export default function App() {
  return (
    <ApolloProvider client={client}>
      <CommandCenterMap />
    </ApolloProvider>
  );
}