.PHONY: up down build demo setup setup-vhosts setup-topology status horizon horizon-probes ssr-build stress sentinel-watch chaos-kill-master chaos-heal

build:
	./vendor/bin/sail build --no-cache

up:
	./vendor/bin/sail up -d

down:
	./vendor/bin/sail down

setup: setup-vhosts setup-topology

setup-vhosts:
	./vendor/bin/sail artisan rabbit-rs:setup-vhosts

setup-topology:
	./vendor/bin/sail artisan rabbit-rs:setup-topology

demo:
	./vendor/bin/sail artisan rabbit-rs:demo --connection=both

demo-rabbit:
	./vendor/bin/sail artisan rabbit-rs:demo --connection=rabbit-rs

demo-redis:
	./vendor/bin/sail artisan rabbit-rs:demo --connection=redis-sentinel

stress:
	./vendor/bin/sail artisan queue-lab:stress --count=100 --sleep-ms=5

status:
	./vendor/bin/sail artisan rabbit-rs:status

horizon:
	./vendor/bin/sail artisan horizon:status

horizon-probes:
	./vendor/bin/sail artisan horizon:ready && ./vendor/bin/sail artisan horizon:alive

ssr-build:
	./vendor/bin/sail npm run build && ./vendor/bin/sail npm run build:ssr

sentinel-watch:
	./vendor/bin/sail exec sentinel-1 valkey-cli -p 26379 --json subscribe "+switch-master" "+failover-end" "+sdown" "+odown"

chaos-kill-master:
	docker compose stop valkey-master

chaos-heal:
	docker compose start valkey-master
