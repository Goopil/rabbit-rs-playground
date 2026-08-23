.PHONY: up down build demo setup-vhosts status workers-simple workers-cluster workers

build:
	./vendor/bin/sail build --no-cache

up:
	./vendor/bin/sail up -d

down:
	./vendor/bin/sail down

setup-vhosts:
	./vendor/bin/sail artisan rabbit-rs:setup-vhosts

demo:
	./vendor/bin/sail artisan rabbit-rs:demo

demo-delay:
	./vendor/bin/sail artisan rabbit-rs:demo --delay

demo-simple:
	./vendor/bin/sail artisan rabbit-rs:demo --setup=simple

demo-cluster:
	./vendor/bin/sail artisan rabbit-rs:demo --setup=cluster

status:
	./vendor/bin/sail artisan rabbit-rs:status

workers-simple:
	@echo "Starting simple workers..."
	@./vendor/bin/sail artisan rabbit-rs:work --queue=simple.default &
	@./vendor/bin/sail artisan rabbit-rs:work --queue=simple.orders &
	@./vendor/bin/sail artisan rabbit-rs:work --queue=simple.notifications &
	@echo "Simple workers started in background"

workers-cluster:
	@echo "Starting cluster workers..."
	@./vendor/bin/sail artisan rabbit-rs:work --queue=cluster.default &
	@./vendor/bin/sail artisan rabbit-rs:work --queue=cluster.orders &
	@./vendor/bin/sail artisan rabbit-rs:work --queue=cluster.notifications &
	@echo "Cluster workers started in background"

workers: workers-simple workers-cluster
