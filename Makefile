.PHONY: cs-check cs-fix architecture-check quality-check

cs-check:
	vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no

cs-fix:
	vendor/bin/php-cs-fixer fix

architecture-check:
	vendor/bin/deptrac analyse --config-file=deptrac.yaml

quality-check: cs-check architecture-check
	composer analyse
