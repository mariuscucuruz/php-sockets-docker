#!/bin/bash

#default values
default_gw_ip=$(ip route show default | grep '\s' | awk '{print $3}')
skip_install=TRUE
skip_download=FALSE
disconnect=FALSE
reconnect=FALSE
connect=TRUE
add_exceptions=FALSE

for params in "$@"; do
  #catch execution parameters
  case $params in
    -u|--skip_update)
      skip_update=TRUE
      ;;
    -d|--skip_download)
      skip_download=TRUE
      ;;
    -c|--connect)
      connect=TRUE
      ;;
    -r|--reconnect)
      reconnect=TRUE
      ;;
    -x|--disconnect)
      disconnect=TRUE
      ;;
    -e|--add_exceptions)
      add_exceptions=TRUE
      ;;
    --country=*)
      country=${1#*=}
      ;;
    \?) echo "Invalid option -$OPTARG" >&2
      ;;
  esac

  shift
done

## throw error if .env not present?
if [ -f .env ]; then
  ## source all environment variables from .env file
  set -o allexport
  source .env
  set +o allexport
else
  echo "Cannot find the .env file. Exiting..."
  #exit 1
fi

if [ -n ${country} ]; then
  country="United Kingdom"
  echo "Defaulting country to ${country@Q}."
fi

if [ -n ${GET_IP_COMMAND} ]; then
  echo "Public IP command not provided. Defaulting to myip.opendns.com"
  GET_IP_COMMAND="dig @resolver1.opendns.com ANY myip.opendns.com +short"
fi

if [ ${skip_install} = FALSE ]; then
  sudo apt update
  echo "Install dependencies...."
  sudo apt-get install -yqq openvpn \
    network-manager-openvpn \
    network-manager-openvpn-gnome \
    network-manager-vpnc \
    curl wget
  sudo systemctl enable openvpn
  sudo service network-manager restart
fi

if [ ${skip_download} = FALSE ]; then
  #sudo apt update && sudo apt upgrade -y
  echo "Download VyprVPN config files...."
  wget https://support.vyprvpn.com/hc/article_attachments/360052617332/Vypr_OpenVPN_20200320.zip -O vypr-configs.zip

  # decompress files and only keep the `openVPN256` ones
  rm -rf vypr-configs/
  unzip vypr-configs.zip -d vypr-configs/
  mv vypr-configs/GF_OpenVPN_20200320/OpenVPN256/* vypr-configs/
  rm vypr-configs.zip && rm -rf vypr-configs/GF_OpenVPN_20200320/

  echo "VyprVPN config files:"
  ls -lha vypr-configs/
fi

echo ">>> Current Public IP: $(${GET_IP_COMMAND}) (w/out VPN)"

if [ ${reconnect} = TRUE ]; then
  disconnect=TRUE
  connect=TRUE
fi

if [ ${disconnect} = TRUE ]; then
  echo "Stopping service..."
  sudo service openvpn stop

  sleep 2 # allow time for VPN connection to be gracefully closed
  echo "Kill'em all!"
  sudo killall -TERM openvpn
  pkill -f openvpn

  # ensure any remaining `openvpn` processes are killed
  ps -ef | grep openvpn | grep -v grep | awk '{print $2}' | xargs -r kill -9

  echo ">> Current Public IP: $(${GET_IP_COMMAND}) (disconnected)"

  if [ ${reconnect} = TRUE ]; then
    connect=TRUE
  else
    connect=FALSE
  fi

  echo ">> VPN Disconnected"
fi

if [ ${connect} = TRUE ]; then
    echo "Starting service..."
    sudo service openvpn start

    echo ">> Attempting to connect to ${MY_VYPRVPN_SERVER} with ${MY_VYPRVPN_PROTOCOL} ..."

    ## save login details from .env to local file
    echo ${MY_VYPRVPN_USER} > vyprlogin.txt \
        && echo ${MY_VYPRVPN_PASS} >> vyprlogin.txt \
        && sudo chmod 400 vyprlogin.txt

    ## attempt connection (in background)
    openvpn --config vypr-configs/${country@Q}.ovpn --auth-user-pass vyprlogin.txt &

    if [ $? -eq 0 ]; then
      echo ">>> Successfully connected to VPN. Current Public IP: $(${GET_IP_COMMAND}) (w/ VPN)"
    else
      echo ">>> Connection to VPN FAILED! Current Public IP: $(${GET_IP_COMMAND}) (no VPN)"
    fi
fi

if [ ${add_exceptions} = TRUE ]; then
    # add exceptions to VPN (admin traffic go via default gateway)
    # otherwise you won't be able to SSH into the VM one VPN is up
    echo "Creating exceptions on ${default_gw_ip}...."
    route add 192.51.132.140 gw ${default_gw_ip} #marius
fi

## exit gracefully
exit 0;
