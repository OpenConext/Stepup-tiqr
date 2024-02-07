Having the azure dev/test certificates in the ci/config folder is a hack to get the acceptance 
tests to run without the need to mount the DevConf config to /config in the container.

Once we start using DevConf for the test-acceptance.yml GitHub Actions integration. This setup should 
be dismantled.
