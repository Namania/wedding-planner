FROM dxflrs/garage:v2.4.1 AS garage

FROM alpine:3.20

COPY --from=garage /garage /usr/local/bin/garage
COPY garage.toml /etc/garage.toml
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 3900 3901

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
