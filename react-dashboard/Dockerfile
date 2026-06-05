FROM node:22-alpine
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
EXPOSE 5173
# Bind to 0.0.0.0 so Vite can pass traffic outside the container
CMD ["npm", "run", "dev", "--", "--host"]