FROM php:8.2-cli

WORKDIR /app
COPY . /app

RUN apt-get update && apt-get install -y \
    git unzip curl ca-certificates \
    imagemagick sshpass \
    python3 python3-pip \
    nodejs npm \
    tesseract-ocr tesseract-ocr-eng \
    && update-ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install gd

RUN npm install -g deobfuscator synchrony

RUN pip3 install --upgrade pip && pip3 install seledroid

ENV PATH="/usr/local/bin:${PATH}"

CMD ["php", "run.php"]
