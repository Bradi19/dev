ARG NGINX_VERSION=1.21.6
FROM docker.io/nginx:alpine
COPY --chown=root:root nginx/prod/nginx.conf /etc/nginx/nginx.conf
