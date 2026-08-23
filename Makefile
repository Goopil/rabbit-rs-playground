.PHONY: up down build demo setup setup-vhosts setup-topology status workers-stop workers-restart workers-status demo-combined

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
	./vendor/bin/sail artisan rabbit-rs:demo

demo-delay:
	./vendor/bin/sail artisan rabbit-rs:demo --delay

demo-simple:
	./vendor/bin/sail artisan rabbit-rs:demo --setup=simple

demo-cluster:
	./vendor/bin/sail artisan rabbit-rs:demo --setup=cluster

demo-combined:
	./vendor/bin/sail artisan rabbit-rs:demo --mode=combined

demo-both:
	./vendor/bin/sail artisan rabbit-rs:demo --mode=both

status:
	./vendor/bin/sail artisan rabbit-rs:status

workers-status:
	./vendor/bin/sail exec supervisorctl status

workers-stop:
	./vendor/bin/sail exec supervisorctl stop rabbit-rs-simple-single rabbit-rs-cluster-single rabbit-rs-simple-all rabbit-rs-cluster-all

workers-restart:
	./vendor/bin/sail exec supervisorctl restart rabbit-rs-simple-single rabbit-rs-cluster-single rabbit-rs-simple-all rabbit-rs-cluster-all
