This is a folder containing scripts related to deployment and development. There are several copies of Officio installed for different clients, and all of them have their own setups. In order to be able to maintain Officio within one repository yet be able to easily customize it for a particular project, these scripts were created.

## select_project

**Usage: php select_project au|ca|bcpnp|ntnp|dm [--config] [--modules] [--composer]**

Used by: development, continuous integration

This script does 3 things:

1. Sets up application project-speficif config
2. Sets up project-specific list of modules
3. Sets up project-specific composer.json and lock files By default script performs all these operations unless a particular one is specified as a flag. TO BE DONE: set up project-specific yarn package.json and lock files.

Use cases:
1. Development - Officio is being deployed from Git for testing or development;
2. Development - changes pulled from Git and there are changed composer dependencies or module lists; for config changes it's recommended to copy them manually;
3. Continuous integration - on a new release on GitHub, GitHub Actions would build project-specific versions of Officio using this script and commit them to the project-specific repositories.

## cleanup_project

**Usage: php cleanup_project au|ca|bcpnp|ntnp|dm**

Used by: continuout integration

This script removes all the stubs and things not related to the currently selected project. It should not be used by developers, but only during continuous integration before committing to project-specific repositories. Therefore it provides isoluation between different projects and better code and client base security.

Use cases:
1. Continous integration. On a new release on GitHub, GitHub Actions would build project-specific version and purge any unrelated information before committing updates to the project-specific repostitory.

## cleanup_project

**Usage: php cleanup_project [au|ca|bcpnp|ntnp|dm] [%composer-package-name 1% ... %composer-package-name N%]**

Used by: development

This script is a help tool for developers which allows to easily update composer lock files for all the project. It's convenient when an update is being done to composer requirements and instead of manually selecting every affected project and running composer update command, this script would take care of the process and rolling things back to development state afterwards.

By default it would update composer locks for all the projects unless a particular project specified. By default it would update all composer packages until particular projects are specified.

Use cases:

1. Development (ONLY if authorized) - a change is made to composer requirements, developer should run this script to also update project-specific lock files



