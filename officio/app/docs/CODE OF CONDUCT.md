# Officio Code of Conduct

### Intro
This documents describes standards and rules for the Officio development. Please read carefully.

### IDE
Developers of Officio HAVE to use PhpStorm as the project is configured for it, i.e. reformatting, cleanup, optimization.

___
### Form development
Field naming convention
There are various forms we develop, however there are rules on how fields should be named. There are three types of fields:
- Synchronizable fields – those are the fields using Officio for synchronizing data between various forms. Should be prefixed with syncA_ (not SyncA, not synca, not Synca) and the rest of the field name should be done in “snake_case”. Example: syncA_user_name, syncA_date_of_birth, syncA_citizenship.
- Regular fields – should be prefixed with the project/form prefix and whole name should be in snake case. Example: dm_citizeship, gd_first_name, bcpnp_invitation_to_apply.
- Repeatables (specific to uForms) – names of repeatables should be done in “CamelCase” starting with uppercase letter (i.e. not camelCase, but CamelCase) – that helps to easily distinguish them from fields. Examples: WorkExperience, TravelHistory, EducationRecords.

___
### Git
#### Pre-commit actions
Before any commit make sure to:
1. Remove any BOM from Officio code (no need to tackle 3rd-party libraries)
2. Convert any line numbers to LF (Unix style)
3. Configure PHPStorm to do automatic code reformatting, cleanup and optimize imports.
   DON'T do automatic code rearrange.

#### Commit messages
Git commit messages should follow the following rules:
1. It has to start with either Fix: / Feature: / Update: / Documentation: 
2. One commit should contain one feature (fix/update/documentation change).
Examples:
•	Fix: bug leading to inability to log in
•	Update: allowed user ID to be 32 characters long
•	Documentation: migration for 5.3.0
Also developers should keep number of commits minimal. If work is not completed, use Git “shelve” functionality to store it. Commits should happen only when work is totally done.

#### Development Flow
We facilitate Git Flow approach but with an additional interim step – “test” branch for testing all new features and fixes. Therefore we have 3 main branches:
- “test” – used on the test server, so whatever needs to be tested, has to be merged into the branch. However, some things are merged into “test” just to keep it up to date.
- “develop” – used for development, has to contain only tested things. Basically this branch contains code which is ready to develop onto production.
- “master” – reflects code which is currently on production. 

Git Flow suggests three general flows:
1. Feature – this stream is used for any new development, which can include not only features, but also fixes, changes, anything. Will be called “Feature” in this document.
2. Release – this stream is used for processing all the developed changes to production. 
3. Hotfix – this is used for applying urgent fixes to production.
These streams define how commits are moved between two main branches: master and develop. Good description on how Git Flow works is here: https://danielkummer.github.io/git-flow-cheatsheet/
However, we add another branch called “test”, this is branch used on our test server. Every flow has to go through test before finishing in “develop” or “master” branches.

Before we go through all the flows in details, read three important rules:
1. Direct commits into “develop” are prohibited.
2. Direct commits into “test” are prohibited.
3. Direct commits into “master” are EXTREMELY prohibited.
All the changes should go through the flows described.

##### Feature
Whenever developer needs to develop any change, which is not urgently needed on production, he/she should go through the feature flow. 

How to start a feature
1. Make sure your “develop” branch is up to date
2. Start new branch named “feature/<%feature-name%> from the latest commit in “develop” branch. Feature name has to be in “kebab case”, i.e. “new-cool-feature-name”. It has to clearly reflect feature purpose.
Now all the work should be done within this branch.
How to test a feature
Whenever a feature is ready for testing, merge it into “test” branch. If there is a feedback which has to be implement, it has to be done within “feature” branch and merged into “test” again. Try to minimize number of merges to keep commit history as clean as possible.
How to finish a feature
Whenever a feature has been tested and is considered completed, it has to be merged into both “test” and “develop” branches. Merging into “test” is neded so test has latest version of this feature. Merging into “develop” makes this feature available for other developers.
Important rule: permission to merge feature into “develop” has to be granted by Andron, sometimes feature finish can be delayed if it cannot be deployed to production yet. In the future this will be facilitated by using pull requests.

##### Release
Whenever we need to deploy changes to production, we have to go through “release” flow. At the moment release can be created only by Andron. 
 
How to start a release
1. Make sure your “develop” branch is up to date
2. Start new branch named “release/<%release-version%> from the latest commit in “develop” branch.
Now release can be polished within this branch. Sometimes this is the point when the code is sent to client, and feedback is implemented in the branch without interrupting main development process. If release needs to be tested, it can be merged into “test” as many times as needed.
How to finish a release
1. Merge it into “test” to keep it up to date.
2. Merge it into “develop”
3. Merge it into “master”
4. Apply version tag to the resulting commit in “master” branch.

##### Hotfix
Sometimes a bug is detected in production, which has to be fixed urgently. In order to facilitate this process without updating the system a hotfix is needed.
 
How to start a hotfix
1. Make sure your “master” branch is up to date
2. Start new branch named “hotfix/<%hotfix-version%> from the latest commit in “master” branch.
Now any fixed can be applied to this branch. If they need to be tested, this branch can be merged in test as many times as needed.
How to finish a hotfix
1. Merge it into “test” to keep it up to date.
2. Merge it into “develop”
3. Merge it into “master”
4. Apply version tag to the resulting commit in “master” branch.
___
### Composer
Developers have to install PHP dependencies using Composer before working on the project and testing it. Rules are:
1. Usage of `composer.phar` is preferrable over `composer` as it's version is aligned with Officio check script;
2. Run `./composer.phar install` before starting work;
3. Don’t run `./composer.phar update` unless it’s being explicitly requested. This includes not committing composer.json and composer.lock files.

Steps to be taken after dependencies update (only for those having permission):
- Check AWS API version if AWS SDK was updated. Specified in constant AWS_API_VERSION belonging to Uniques_Cloud class. API versions can be found here: https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-s3-2006-03-01.html 
Permission for updating Composer packages can be granted by Andron Kocherhan <andron@uniques.ca>.

---
### Yarn
Developers have to install JS dependencies using Yarn before working on the project and testing it. Rules are:
1. Run `yarn install` before starting work;
2. Don’t run `yarn update` unless it’s being explicitly requested. This includes not committing package.json and yarn.lock files.
