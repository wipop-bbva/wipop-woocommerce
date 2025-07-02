FROM node:18-bookworm

RUN apt-get update -y \
 && apt-get install -y --no-install-recommends php-cli php-xml php-curl php-mbstring unzip git \
  && curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /workspace
CMD ["bash"]