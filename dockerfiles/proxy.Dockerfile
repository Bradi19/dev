ARG NGINX_VERSION=1.21.6
FROM nginx:${NGINX_VERSION}-alpine
# COPY --chown=root:root nginx.conf /etc/nginx/nginx.conf
