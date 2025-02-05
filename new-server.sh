#!/bin/bash

sudo apt update
sudo apt install apache2 -y
sudo apt-get install ca-certificates apt-transport-https software-properties-common -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
while [[ "$phpversion" != "8.3" && "$phpversion" != "8.2" && "$phpversion" != "8.1" && "$phpversion" != "8" && "$phpversion" != "7.4" ]] ; do
    read -p "Enter PHP Version (8.3/8.2/8.1/8/7.4): " phpversion
done
sudo apt install php$phpversion -y
sudo apt install php$phpversion-{cli,common,mbstring,mysql,xml,curl,gd,imagick,bcmath,zip,tokenizer,dom} openssl -y
sudo a2enmod ssl rewrite
sudo service apache2 restart
