# The web app (app.trymakanapa.com): built with Vite, served as static files by nginx.
# Dokploy: build path /web, and the VITE_* values as build arguments (they're baked into the
# bundle at build time, so changing one needs a rebuild).
FROM node:22-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
ARG VITE_API_BASE_URL
ARG VITE_GOOGLE_CLIENT_ID
ARG VITE_GOOGLE_MAPS_KEY
ARG VITE_GOOGLE_MAPS_MAP_ID
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL \
    VITE_GOOGLE_CLIENT_ID=$VITE_GOOGLE_CLIENT_ID \
    VITE_GOOGLE_MAPS_KEY=$VITE_GOOGLE_MAPS_KEY \
    VITE_GOOGLE_MAPS_MAP_ID=$VITE_GOOGLE_MAPS_MAP_ID
RUN npm run build

FROM nginx:1.27-alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
