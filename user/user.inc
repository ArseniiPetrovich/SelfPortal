<?php
function update_nginx_config() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE);
    $query ="SELECT `site_name`,`domain` ,`rhost`,`rport`,`status` FROM `proxysites`, `domains` WHERE `proxysites`.`domain_id`=`domains`.`domain_id` AND `domains`.`shared`=1 AND `proxysites`.`status`!='Disabled'";
        if ($result = mysqli_query($conn, $query)) 
        {   
            $config="##SELFPORTAL\n ";
            while ($site=mysqli_fetch_array($result)) {
                if ($site['status']=="HTTPS")
                    $config .="
                        server {
                            listen 80;
                            server_name $site[site_name].$site[domain];
                            return 301 https://$site[site_name].$site[domain]\$request_uri;
                        }
                        server {
                            listen 443 ssl;
                            server_name $site[site_name].$site[domain];
                            ssl_certificate           /etc/nginx/$site[domain].crt;
                            ssl_certificate_key       /etc/nginx/$site[domain].key;
                            ssl_protocols TLSv1.2;
                            ssl_ciphers 'ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA256';
                            ssl_prefer_server_ciphers on;
    
                            location / {
                                proxy_pass http://$site[rhost]:$site[rport];
                                proxy_set_header X-Real-IP \$remote_addr;
                                proxy_set_header Host \$http_host;
                                proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
                                proxy_set_header X-Forwarded-Host \$host;
                                proxy_set_header X-Forwarded-Server \$host;
                            }
                        }";
                else {
                    $config .="server {
                                listen 80;
                                server_name $site[site_name].$site[domain];

                                location / {
                                    proxy_pass http://$site[rhost]:$site[rport]/;
                                    proxy_set_header   X-Real-IP \$remote_addr;
                                    proxy_set_header   Host \$http_host;
                                    proxy_set_header   X-Forwarded-For \$proxy_add_x_forwarded_for;
                                    proxy_set_header X-Forwarded-Host \$host;
                                    proxy_set_header X-Forwarded-Server \$host;
                                }
                               }";
                }
            }
            file_put_contents(NGINX_FILE, $config, LOCK_EX);
        }
        
$out=shell_exec('sudo /usr/sbin/nginx -s reload');
$conn->close();
}

?>
