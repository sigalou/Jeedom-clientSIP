#!/bin/bash
cd $1
touch /tmp/clientSIP
echo "Début de l'installation"
echo 0 > /tmp/clientSIP
echo "Installation PicoTTS"
echo 70 > /tmp/clientSIP
arch=`arch`;
if [[ $arch == "armv6l" || $arch == "armv7l" ]]
  then
    sudo apt-get install -y libsox-fmt-mp3 sox
    sudo dpkg -i libttspico-data_1.0+git20130326-3_all.deb
    sudo dpkg -i libttspico0_1.0+git20130326-3_armhf.deb
    sudo dpkg -i libttspico-utils_1.0+git20130326-3_armhf.deb
  else
    sudo add-apt-repository non-free
    sudo add-apt-repository contrib
    sudo apt-get update
    sudo apt-get install -y libsox-fmt-mp3 sox libttspico-utils
fi

echo "Ajout de www-data dans le groupe audio"
echo 90 > /tmp/clientSIP
sudo usermod -a -G audio `whoami`

echo "Fin de l'installation"
rm /tmp/clientSIP
