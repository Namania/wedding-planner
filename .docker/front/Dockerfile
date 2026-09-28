# Image frontend : le SPA Vue compilé, servi en statique par nginx.
#
# Build depuis la racine du dépôt : docker build -f .docker/front/Dockerfile .

FROM node:20-alpine AS build

WORKDIR /app

COPY resources/package*.json ./
RUN npm ci

COPY resources/ .

# Le build utilise resources/.env.production, qui ne contient que des jetons
# (__RUNTIME_...__) : les vraies valeurs sont injectées au démarrage du
# conteneur par l'entrypoint. L'image reste ainsi valable pour n'importe quel
# environnement.
# vite.config.ts écrit dans ../public/build, soit /public/build ici.
RUN npm run build

FROM nginx:1.27-alpine

# proxy.conf est hors de conf.d/ : nginx y inclut automatiquement tout *.conf,
# ce qui chargerait ses directives une seconde fois au niveau http.
COPY .docker/front/nginx.conf /etc/nginx/conf.d/default.conf
COPY .docker/front/proxy.conf /etc/nginx/proxy.conf
COPY --from=build /public/build /usr/share/nginx/html
COPY .docker/front/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

ENV BUILD_DIR=/usr/share/nginx/html
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]
