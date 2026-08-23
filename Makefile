.PHONY: up down build demo setup setup-vhosts setup-topology status workers-stop workers-restart workers-status demo-combined demo-stress check-queues

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

demo-stress:
	./vendor/bin/sail artisan rabbit-rs:demo --mode=combined --count=625

check-queues:
	@echo "Checking combined queue depths..."
	@for vhost in default orders notifications; do \
		vencoded=$$(python3 -c "import urllib.parse; print(urllib.parse.quote('/'+'$$vhost'))"); \
		echo "  Vhost /$$vhost:"; \
		curl -s -u guest:guest "http://localhost:15672/api/queues/$$vencoded" 2>/dev/null | \
		python3 -c "import json,sys; [print(f'    {q[\"name\"]}: {q[\"messages\"]} msgs') for q in json.load(sys.stdin) if 'all' in q['name']]" 2>/dev/null; \
		curl -s -u guest:guest "http://localhost:15673/api/queues/$$vencoded" 2>/dev/null | \
		python3 -c "import json,sys; [print(f'    {q[\"name\"]}: {q[\"messages\"]} msgs') for q in json.load(sys.stdin) if 'all' in q['name']]" 2>/dev/null; \
	done
	@echo ""
	@echo "Checking if all combined queues are drained..."
	@total=$$(for port in 15672 15673; do \
		for vhost in default orders notifications; do \
			vencoded=$$(python3 -c "import urllib.parse; print(urllib.parse.quote('/'+'$$vhost'))"); \
			curl -s -u guest:guest "http://localhost:$$port/api/queues/$$vencoded" 2>/dev/null | \
			python3 -c "import json,sys; print(sum(q['messages'] for q in json.load(sys.stdin) if 'all' in q['name']))" 2>/dev/null; \
		done; \
	done); \
	total=$$(echo $$total | python3 -c "import sys; print(sum(int(x) for x in sys.stdin.read().split()))"); \
	if [ "$$total" = "0" ]; then \
		echo "✓ All combined queues drained!"; \
	else \
		echo "✗ $$total messages still pending in combined queues"; \
	fi

status:
	./vendor/bin/sail artisan rabbit-rs:status

workers-status:
	./vendor/bin/sail exec supervisorctl status

workers-stop:
	./vendor/bin/sail exec supervisorctl stop rabbit-rs-simple-single rabbit-rs-cluster-single rabbit-rs-simple-all rabbit-rs-cluster-all

workers-restart:
	./vendor/bin/sail exec supervisorctl restart rabbit-rs-simple-single rabbit-rs-cluster-single rabbit-rs-simple-all rabbit-rs-cluster-all
