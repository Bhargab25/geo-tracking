<?php

namespace App\Traits;

trait ParsesSpatialAttributes
{
    /**
     * Parse EWKB hex representation of a Point geometry into X and Y coordinates.
     *
     * @param string $hex
     * @return array{x: float, y: float}|null
     */
    public static function parsePointEwkb(string $hex): ?array
    {
        if (!preg_match('/^[0-9a-fA-F]+$/', $hex)) {
            return null;
        }

        $binary = hex2bin($hex);
        if (strlen($binary) < 21) {
            return null;
        }

        // Read endianness (1 byte): 0 = big endian, 1 = little endian
        $endian = unpack('C', substr($binary, 0, 1))[1];
        $isLittle = ($endian === 1);

        // Read geometry type (4 bytes)
        $typeFormat = $isLittle ? 'V' : 'N';
        $type = unpack($typeFormat, substr($binary, 1, 4))[1];

        // Check for SRID flag (0x20000000)
        $hasSRID = (bool) ($type & 0x20000000);
        $cleanType = $type & 0x0FFFFFFF;

        if ($cleanType !== 1) { // 1 = Point
            return null;
        }

        $offset = 5;
        if ($hasSRID) {
            $offset += 4;
        }

        if (strlen($binary) < $offset + 16) {
            return null;
        }

        $isMachineLittle = (pack('L', 1) === pack('V', 1));
        $xBytes = substr($binary, $offset, 8);
        $yBytes = substr($binary, $offset + 8, 8);

        if ($isLittle !== $isMachineLittle) {
            $xBytes = strrev($xBytes);
            $yBytes = strrev($yBytes);
        }

        $x = unpack('d', $xBytes)[1];
        $y = unpack('d', $yBytes)[1];

        return [
            'x' => (float) $x,
            'y' => (float) $y,
        ];
    }

    /**
     * Parse EWKB hex representation of a Polygon geometry into coordinate arrays.
     *
     * @param string $hex
     * @return array<array{0: float, 1: float}>|null
     */
    public static function parsePolygonEwkb(string $hex): ?array
    {
        if (!preg_match('/^[0-9a-fA-F]+$/', $hex)) {
            return null;
        }

        $binary = hex2bin($hex);
        if (strlen($binary) < 9) {
            return null;
        }

        // Read endianness (1 byte): 0 = big endian, 1 = little endian
        $endian = unpack('C', substr($binary, 0, 1))[1];
        $isLittle = ($endian === 1);

        // Read geometry type (4 bytes)
        $typeFormat = $isLittle ? 'V' : 'N';
        $type = unpack($typeFormat, substr($binary, 1, 4))[1];

        // Check for SRID flag (0x20000000)
        $hasSRID = (bool) ($type & 0x20000000);
        $cleanType = $type & 0x0FFFFFFF;

        if ($cleanType !== 3) { // 3 = Polygon
            return null;
        }

        $offset = 5;
        if ($hasSRID) {
            $offset += 4;
        }

        if (strlen($binary) < $offset + 4) {
            return null;
        }

        // Read number of rings (4 bytes uint32)
        $uint32Format = $isLittle ? 'V' : 'N';
        $numRings = unpack($uint32Format, substr($binary, $offset, 4))[1];
        $offset += 4;

        if ($numRings === 0) {
            return [];
        }

        // Focus on the outer ring (first ring)
        if (strlen($binary) < $offset + 4) {
            return null;
        }
        $numPoints = unpack($uint32Format, substr($binary, $offset, 4))[1];
        $offset += 4;

        if (strlen($binary) < $offset + ($numPoints * 16)) {
            return null;
        }

        $isMachineLittle = (pack('L', 1) === pack('V', 1));
        $coordinates = [];

        for ($i = 0; $i < $numPoints; $i++) {
            $xBytes = substr($binary, $offset, 8);
            $yBytes = substr($binary, $offset + 8, 8);
            $offset += 16;

            if ($isLittle !== $isMachineLittle) {
                $xBytes = strrev($xBytes);
                $yBytes = strrev($yBytes);
            }

            $x = unpack('d', $xBytes)[1];
            $y = unpack('d', $yBytes)[1];

            // Leaflet reads coordinates as [lat, lng], where:
            // x is Longitude (lng) and y is Latitude (lat)
            $coordinates[] = [(float) $y, (float) $x];
        }

        return $coordinates;
    }
}
