# @see https://github.com/nginxinc/docker-nginx/tree/6c7dde575cecad8d6f8ec486ca9d4b76358e643e/mainline/buster
FROM nginx:1.19.0

ARG DEBIAN_FRONTEND=noninteractive

LABEL \
   Maintainer="TYPO3 Documentation Team" \
   Description="This image provides an Nginx server for creating new TYPO3 Exception pages." \
   Vendor="t3docs"

# Smoke test
RUN nginx -V
