# renovate: datasource=npm depName=jscpd
JSCPD_VERSION := 5.2.0

.PHONY: check cpd

# Same gates as CI: coding standard, PHPStan, PHPUnit, duplication.
check:
	composer check
	$(MAKE) cpd

# Runs on the host (needs Node/npx). Config lives in .jscpd.json.
cpd:
	npx --yes jscpd@$(JSCPD_VERSION)
