FROM php:8.2-cli

WORKDIR /app
COPY . /app

RUN apt-get update && apt-get install -y \
    git unzip curl \
    imagemagick sshpass \
    python3 python3-pip \
    nodejs npm \
    tesseract-ocr tesseract-ocr-eng \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install gd

RUN npm install -g deobfuscator

RUN ln -s $(npm bin -g)/synchrony /usr/local/bin/synchrony || true

RUN pip3 install --upgrade pip && pip3 install seledroid

# ENV
ENV ENV=1

CMD ["php", "run.php"]