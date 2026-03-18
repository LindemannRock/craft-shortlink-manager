# Changelog

## [5.16.3](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.16.2...v5.16.3) (2026-03-18)


### Bug Fixes

* **redirect:** change shortlink code to use slug instead of code ([619b622](https://github.com/LindemannRock/craft-shortlink-manager/commit/619b622283f5398d526cc865f42fbe0b08737243))

## [5.16.2](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.16.1...v5.16.2) (2026-03-18)


### Bug Fixes

* **config:** change default HTTP redirect code to 302 ([303b2c2](https://github.com/LindemannRock/craft-shortlink-manager/commit/303b2c2bcf6672dfb9230123dcd6bb7ec03b1d44))
* **http:** change default HTTP redirect code from 301 to 302 ([ad47d89](https://github.com/LindemannRock/craft-shortlink-manager/commit/ad47d89629d2196666685a7f4eb5c01009a9bf3a))

## [5.16.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.16.0...v5.16.1) (2026-03-17)


### Miscellaneous Chores

* **workflow:** update permissions in release-please.yml ([44ba030](https://github.com/LindemannRock/craft-shortlink-manager/commit/44ba030fb152f6c52eb67aa00f938ba97d5e2a6d))

## [5.16.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.15.1...v5.16.0) (2026-03-17)


### Features

* **analytics:** streamline IP handling in trackClick method ([e0ba4b5](https://github.com/LindemannRock/craft-shortlink-manager/commit/e0ba4b54294938db4030004c294f2c0fa5e9e1f5))

## [5.15.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.15.0...v5.15.1) (2026-03-17)


### Bug Fixes

* **analytics:** streamline click tracking and data storage ([1c1313b](https://github.com/LindemannRock/craft-shortlink-manager/commit/1c1313b8bcb3d9990cd26a94916a43f40209e6e1))

## [5.15.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.14.0...v5.15.0) (2026-03-17)


### Features

* add installation experience details for ShortLink Manager ([de549bb](https://github.com/LindemannRock/craft-shortlink-manager/commit/de549bb49d25d19d0e71ae230d5cf3f661598722))
* **analytics:** add build process for analytics JavaScript assets ([e07abfe](https://github.com/LindemannRock/craft-shortlink-manager/commit/e07abfe663654bf2b9d82fadab26c06861c4e305))
* **import/export:** add import/export functionality with history tracking ([5efef73](https://github.com/LindemannRock/craft-shortlink-manager/commit/5efef73045b88251128cf947d754271535bb38c0))
* **import/export:** add new fields for QR code customization and query params ([d240c96](https://github.com/LindemannRock/craft-shortlink-manager/commit/d240c96f6a41956e8ab59be664ff22458fecc26f))
* **import/export:** add permission check for import/export functionality ([beb937a](https://github.com/LindemannRock/craft-shortlink-manager/commit/beb937a4125810831ce61d21b34d360254647785))
* **import/export:** enhance import/export functionality with date fields ([0223c1b](https://github.com/LindemannRock/craft-shortlink-manager/commit/0223c1b3c258e9ffd73e2f150ad8425bc1fa96ca))
* **import/export:** update CSV export and import fields for postDate ([60962a3](https://github.com/LindemannRock/craft-shortlink-manager/commit/60962a36690243fb093c12422ad7bab422178c43))
* **records:** add Folder, Tag, and ShortLinkTag records ([82f0c62](https://github.com/LindemannRock/craft-shortlink-manager/commit/82f0c622fbd2a72dcc4c95d6679f8f0f213c9e75))
* **services:** implement TaxonomyService for folder and tag management ([82f0c62](https://github.com/LindemannRock/craft-shortlink-manager/commit/82f0c622fbd2a72dcc4c95d6679f8f0f213c9e75))
* **settings:** add usePrefix option for shortlink URL generation ([d64b19d](https://github.com/LindemannRock/craft-shortlink-manager/commit/d64b19de727f43cf908073d5f1bf52cbdf412291))
* **templates:** add folder management UI ([82f0c62](https://github.com/LindemannRock/craft-shortlink-manager/commit/82f0c622fbd2a72dcc4c95d6679f8f0f213c9e75))
* **templates:** enhance import/export functionality with folders and tags ([82f0c62](https://github.com/LindemannRock/craft-shortlink-manager/commit/82f0c622fbd2a72dcc4c95d6679f8f0f213c9e75))


### Bug Fixes

* **migrations:** add slugPrefix and adjust usePrefix column position ([124ec89](https://github.com/LindemannRock/craft-shortlink-manager/commit/124ec89ab85e3027d549ab3e023ab234cb50caea))
* **settings:** remove redundant submit button from settings forms ([5aac23d](https://github.com/LindemannRock/craft-shortlink-manager/commit/5aac23d5cea8c9309b582947d916db4f9a610582))
* **settings:** validate shortlink base URL to prevent spaces ([3bcdc85](https://github.com/LindemannRock/craft-shortlink-manager/commit/3bcdc85bad62ec0bbe88305672f15d4a11be69d6))
* **shortlink:** handle existing links switched from vanity to code ([1f25853](https://github.com/LindemannRock/craft-shortlink-manager/commit/1f25853ca1ce47d48ed09cba2e02681691ebd810))

## [5.14.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.13.0...v5.14.0) (2026-03-04)


### Features

* add complete EN/DE translation ([d215d4d](https://github.com/LindemannRock/craft-shortlink-manager/commit/d215d4ddc385fa26e0f245d70db53c77dade0690))


### Bug Fixes

* **jobs:** implement RetryableJobInterface in CleanupAnalyticsJob ([11e4fa9](https://github.com/LindemannRock/craft-shortlink-manager/commit/11e4fa9bdf606d976528f404763f0e8bbadea523))
* **settings, qr-code:** improve translations and error messages ([f011c59](https://github.com/LindemannRock/craft-shortlink-manager/commit/f011c594cb58252750c095f9885cb6bf91951f8d))
* **settings, ShortLinkManager, ShortLink:** improve URL handling and validation ([159f2d7](https://github.com/LindemannRock/craft-shortlink-manager/commit/159f2d7db5477f7cfb5827fb402d6f1c5fac3cbf))
* **settings, validation, templates:** improve settings validation and error handling ([92fac44](https://github.com/LindemannRock/craft-shortlink-manager/commit/92fac44593b2ad23639f6d45ac46eb79c31281cd))

## [5.13.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.12.0...v5.13.0) (2026-02-20)


### Features

* **analytics:** add new analytics types and format recent clicks ([b57d1b8](https://github.com/LindemannRock/craft-shortlink-manager/commit/b57d1b81615bec287f74562aaac5c6818e338354))
* **shortlink:** add directRedirect setting for server-side redirects ([91eb418](https://github.com/LindemannRock/craft-shortlink-manager/commit/91eb4187e3f6f6f0662139738b4b314e3ad29557))
* **shortlink:** add site-aware shortlink routes and base URL settings ([34c3c0f](https://github.com/LindemannRock/craft-shortlink-manager/commit/34c3c0fd67d104319131ea63c30f345193706f79))


### Bug Fixes

* validate analytics type parameter and replace getenv() ([722d1ea](https://github.com/LindemannRock/craft-shortlink-manager/commit/722d1eade54b9567e181016adef2b5879a66deb0))


### Miscellaneous Chores

* **.gitignore:** clean up ignored files and add internal directory ([ec51c42](https://github.com/LindemannRock/craft-shortlink-manager/commit/ec51c422b49f8e4f1f00a53fdea54039dfa329b5))
* add .gitattributes with export-ignore for Packagist distribution ([fc993cd](https://github.com/LindemannRock/craft-shortlink-manager/commit/fc993cdfc60ddaf773331feb46a1914fce77dcb6))
* switch to Craft License for commercial release ([0f8f8e5](https://github.com/LindemannRock/craft-shortlink-manager/commit/0f8f8e587071ca55427888c2f79085dd1be2b9cc))

## [5.12.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.11.0...v5.12.0) (2026-02-07)


### Features

* **analytics:** add export format validation and enhance QR code generation permissions ([2289632](https://github.com/LindemannRock/craft-shortlink-manager/commit/22896325e3f738351136c49b2f36ddbfca99b834))
* **analytics:** enhance analytics data handling and sanitization ([d605cd6](https://github.com/LindemannRock/craft-shortlink-manager/commit/d605cd6efafcd2de50a1403d62d5b69f38a4ecb1))
* **analytics:** Enhance analytics functionality with user permissions and site filtering ([e1ca55b](https://github.com/LindemannRock/craft-shortlink-manager/commit/e1ca55b7e0388f8e9d582983729944bfe42f443c))

## [5.11.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.10.0...v5.11.0) (2026-02-05)


### Features

* **analytics:** enhance analytics functionality and UI ([f46f049](https://github.com/LindemannRock/craft-shortlink-manager/commit/f46f0498f78b95f91bae1d353d4d7c087649abec))
* **settings:** add passQueryParams option for query parameter handling ([d13589d](https://github.com/LindemannRock/craft-shortlink-manager/commit/d13589d15c5436c0f174bfea67cd6b36bdac975c))


### Bug Fixes

* **AnalyticsController, QrCodeController, SettingsController:** enforce permissions for analytics and cache actions ([2530b4b](https://github.com/LindemannRock/craft-shortlink-manager/commit/2530b4b9b0e994a364f017c5d5c5e3afab2c8c0c))
* **RedirectController:** handle malformed URLs and protocol-relative links ([12e0d58](https://github.com/LindemannRock/craft-shortlink-manager/commit/12e0d586b3707b06b6bc645ae7da22cde0fbfeef))
* **ShortLinkManager:** update [@since](https://github.com/since) annotation for getCpSections method to 5.11.0 ([3731baf](https://github.com/LindemannRock/craft-shortlink-manager/commit/3731baf369ba5d93050c5f642d436129dbe16143))


### Miscellaneous Chores

* **dependencies:** Remove matomo/device-detector from composer.json ([e22887f](https://github.com/LindemannRock/craft-shortlink-manager/commit/e22887fc299ccdb03b2790e801b97baaee989e7a))
* update package-lock.json and package.json for dependency management ([c3bc52d](https://github.com/LindemannRock/craft-shortlink-manager/commit/c3bc52d117cb0566c93788cc670d106616b4a54b))
* update package.json to include author and company information ([0c0d2da](https://github.com/LindemannRock/craft-shortlink-manager/commit/0c0d2da50c875247c1157df2cf2ab84711bb7e6c))

## [5.10.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.9.0...v5.10.0) (2026-01-26)


### Features

* replace Craft plugin calls with PluginHelper methods for consistency ([e219ba6](https://github.com/LindemannRock/craft-shortlink-manager/commit/e219ba6f839a6a36b7b27ecc52046ee438d94c14))


### Bug Fixes

* **jobs:** prevent duplicate scheduling of CleanupAnalyticsJob ([6d08934](https://github.com/LindemannRock/craft-shortlink-manager/commit/6d089345eb81778e09fd32dfa82bf00ee3a59e4d))

## [5.9.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.8.1...v5.9.0) (2026-01-21)


### Features

* Add configurable geo IP provider settings with HTTPS support ([4730d8c](https://github.com/LindemannRock/craft-shortlink-manager/commit/4730d8c535730ad8553f4097cfbfe9722144e60e))


### Bug Fixes

* swap QR Code and Behavior settings links and update heading in General Settings ([f74eccd](https://github.com/LindemannRock/craft-shortlink-manager/commit/f74eccda39aca1491fe2ef0f09ce3e9b848d9409))

## [5.8.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.8.0...v5.8.1) (2026-01-16)


### Bug Fixes

* reorganize and standardize analytics templates ([919a245](https://github.com/LindemannRock/craft-shortlink-manager/commit/919a245b1a9c7b78480db71b0eae82be6e499794))
* update cache location message to use shortlinkHelper for dynamic path ([0fd1669](https://github.com/LindemannRock/craft-shortlink-manager/commit/0fd166900606f4f948e78811aea967810abc371f))
* update filename generation to use lowerDisplayName for analytics export ([0f96df2](https://github.com/LindemannRock/craft-shortlink-manager/commit/0f96df2d29bb27b5f6166d77854cb81eee647efe))
* update hardcoded cache paths with PluginHelper for consistency ([130bd28](https://github.com/LindemannRock/craft-shortlink-manager/commit/130bd2888e418870719bac3360eee384e28929e8))
* update PluginHelper bootstrap to include download permissions for logging ([eec20fd](https://github.com/LindemannRock/craft-shortlink-manager/commit/eec20fd6b569d738d98d965fa497deb4f93533a6))

## [5.8.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.7.0...v5.8.0) (2026-01-12)


### Features

* Format cache file counts and total clicks in cache clearing buttons ([c843262](https://github.com/LindemannRock/craft-shortlink-manager/commit/c84326261e84dd14a970013b5ea2bf41a1f67b10))
* Update terminology from "Clicks" to "Interactions" and enhance link display in top links widget ([530e9aa](https://github.com/LindemannRock/craft-shortlink-manager/commit/530e9aa63f6352c9848d4a7d1f417d9c858df8c1))

## [5.7.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.6.0...v5.7.0) (2026-01-10)


### Features

* Replace custom country name retrieval with GeoHelper utility ([0dcc15b](https://github.com/LindemannRock/craft-shortlink-manager/commit/0dcc15b292ea38259b2390dc1aaeeb2a8e40132c))

## [5.6.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.5.0...v5.6.0) (2026-01-08)


### Features

* enhance documentation for custom short domains and update settings handling ([a22b7c5](https://github.com/LindemannRock/craft-shortlink-manager/commit/a22b7c58c2824b32202b3a30ae8d886e80348212))
* make element selection translatable per site for manual shortlinks ([5a1104d](https://github.com/LindemannRock/craft-shortlink-manager/commit/5a1104da21beb0cb0355c719d6aa20d9ef60ad3b))
* Refactor permissions to use grouped nested structure ([eaf3b05](https://github.com/LindemannRock/craft-shortlink-manager/commit/eaf3b0521d54de5a86a80a4b6d98f4bd278dd5b0))
* update README to include per-site translatable destinations and enhance export formats ([855d782](https://github.com/LindemannRock/craft-shortlink-manager/commit/855d782f1ce762a1bfa5b2efcf6f951237a63692))
* update Settings model methods to protected and add setDefaultQrLogoId method ([7e2690e](https://github.com/LindemannRock/craft-shortlink-manager/commit/7e2690e0bc6d0ceff5c2f5a155e2fdd34bb9892d))


### Miscellaneous Chores

* remove local composer.lock file ([705b8b8](https://github.com/LindemannRock/craft-shortlink-manager/commit/705b8b80b00e7e296ef6a0c0e44f29d9221d7ea0))

## [5.5.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.4.2...v5.5.0) (2026-01-06)


### Features

* migrate to shared base plugin ([e74da6f](https://github.com/LindemannRock/craft-shortlink-manager/commit/e74da6f349f972837e68fd2e0b22ebd80c2c67af))

## [5.4.2](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.4.1...v5.4.2) (2026-01-05)


### Bug Fixes

* add tab-content class to analytics sections for improved styling ([4b4c0ec](https://github.com/LindemannRock/craft-shortlink-manager/commit/4b4c0ec0f659c0997db2378ccbec3e5b361de8ec))

## [5.4.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.4.0...v5.4.1) (2025-12-19)


### Bug Fixes

* Refactor site selection logic in AnalyticsController for improved clarity ([60d38a3](https://github.com/LindemannRock/craft-shortlink-manager/commit/60d38a37023d9a7f6ee20b9e371014d7aa681f3d))

## [5.4.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.3.3...v5.4.0) (2025-12-19)


### Features

* Add Traffic & Devices tab with device analytics charts ([0c5fbd3](https://github.com/LindemannRock/craft-shortlink-manager/commit/0c5fbd3eb3c3a284666edc6e3fc8536b6317b664))


### Bug Fixes

* improve cache duration settings and user feedback ([f278cca](https://github.com/LindemannRock/craft-shortlink-manager/commit/f278cca6741b1ec721935e426643a81f0d12c34b))
* Rename 'Hits' label to 'Interactions' in ShortLink elements and templates ([ea90868](https://github.com/LindemannRock/craft-shortlink-manager/commit/ea9086845fdb424a3f7199357f614a66df602fcd))
* update cache label to use display name and trim whitespace in settings methods ([d134c38](https://github.com/LindemannRock/craft-shortlink-manager/commit/d134c38745f298a07c4df3a03d68fc46d6cb87b6))
* update country name mapping in analytics results ([0ece6ac](https://github.com/LindemannRock/craft-shortlink-manager/commit/0ece6ac137be3e821830b33f3981727d59acc0f2))

## [5.3.3](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.3.2...v5.3.3) (2025-12-16)


### Bug Fixes

* update icon return value in ShortLinkManagerUtility ([e4f0951](https://github.com/LindemannRock/craft-shortlink-manager/commit/e4f09519a6bb2be44809f86884b7f9593577cc39))

## [5.3.2](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.3.1...v5.3.2) (2025-12-16)


### Bug Fixes

* update time formatting in analytics dashboard to use locale settings ([cf2ad60](https://github.com/LindemannRock/craft-shortlink-manager/commit/cf2ad6025677349db175c81ac62b0c6f8e9b3e8b))

## [5.3.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.3.0...v5.3.1) (2025-12-16)


### Bug Fixes

* simplify redirect manager events to only include slug-change ([ad4cd18](https://github.com/LindemannRock/craft-shortlink-manager/commit/ad4cd1848f9f30e1a52f1e4c58759823322a94e1))

## [5.3.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.2.0...v5.3.0) (2025-12-16)


### Features

* add cache storage method configuration for different environments ([186fd37](https://github.com/LindemannRock/craft-shortlink-manager/commit/186fd37257b2ebd4afe4866ac99b4da56ef29aa8))
* add cache storage method configuration to Install migration ([4a1c9f4](https://github.com/LindemannRock/craft-shortlink-manager/commit/4a1c9f46ee4a435c3d3e848370a1bd285831fa7d))
* add Info Box component and enhance analytics display with number formatting ([6b3ee45](https://github.com/LindemannRock/craft-shortlink-manager/commit/6b3ee4514113a66b7bb49cf053a681ec481f6c70))
* enhance analytics display and timezone handling in AnalyticsController and AnalyticsService ([c636a3f](https://github.com/LindemannRock/craft-shortlink-manager/commit/c636a3fc6c75a93f2868ccfaef98f315f10ea1fa))
* implement Redis caching support and enhance cache management in ShortLinkManager ([a6429d9](https://github.com/LindemannRock/craft-shortlink-manager/commit/a6429d91eb6da1ed55ea9530905fd1f77f5cced6))
* update icon to 'link-simple.svg' and refine Redis cache display in index template ([167dd39](https://github.com/LindemannRock/craft-shortlink-manager/commit/167dd39cba300cc23cec2aeb317eba3e7fa34a4d))

## [5.2.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.1.6...v5.2.0) (2025-12-03)


### Features

* add [@since](https://github.com/since) 5.0.0 annotation to multiple controllers, services, and models ([e7c3e39](https://github.com/LindemannRock/craft-shortlink-manager/commit/e7c3e39cb635e05275ce5c8da305af8e0750854c))
* add expired message handling to ShortLink management ([ac732c9](https://github.com/LindemannRock/craft-shortlink-manager/commit/ac732c9727edae433ef461b0281c25896dcfc60c))
* **analytics:** add AJAX endpoint for fetching link analytics data ([3f3b835](https://github.com/LindemannRock/craft-shortlink-manager/commit/3f3b835a820a3ad59a04731456729663c7781e6e))
* **analytics:** add default location settings for local development based on environment variables ([fb6f5f3](https://github.com/LindemannRock/craft-shortlink-manager/commit/fb6f5f3379a6d6dc170ede291e5d1a05b4407009))
* **analytics:** enhance getTopLinks and getLinkAnalytics methods to include site name in results ([1819f30](https://github.com/LindemannRock/craft-shortlink-manager/commit/1819f306229418e66592cb2d997e24dafd8e6558))
* **development:** add PHPStan and EasyCodingStandard configurations ([1a40413](https://github.com/LindemannRock/craft-shortlink-manager/commit/1a404134d984c5c95b7c01374d9be2d8569a1402))
* enhance short link status display with additional states ([f71a46b](https://github.com/LindemannRock/craft-shortlink-manager/commit/f71a46bd813dcf129107b145f909f688a5677927))
* **expiration:** add site-specific expired message handling and remove expiration toggle ([d1ef24f](https://github.com/LindemannRock/craft-shortlink-manager/commit/d1ef24f5ab54e885d78776458dbc96bd0a044052))
* **layouts:** add new layout for control panel and update existing templates to use it ([da78d85](https://github.com/LindemannRock/craft-shortlink-manager/commit/da78d85e38b36cf80bcfc32b6bbfe95c2ca2b939))
* **qr-code:** update QR code logo handling and improve sidebar display ([34a7776](https://github.com/LindemannRock/craft-shortlink-manager/commit/34a7776f05c39e7ec698b81a2fd97edeee3b0ffb))
* **redirect:** implement 404 handling through Redirect Manager integration ([b5978df](https://github.com/LindemannRock/craft-shortlink-manager/commit/b5978dfbb3239839026340f5c9fc114f3ad12223))
* **shortlink-field:** add Link field integration and enhance settings UI ([42672b0](https://github.com/LindemannRock/craft-shortlink-manager/commit/42672b06131ecd9447ff81990c50297ba40fc1c5))
* **shortlink-status:** add pending status and update query handling for pending links ([e0817fd](https://github.com/LindemannRock/craft-shortlink-manager/commit/e0817fdc3c1064b085255df868617c27710f7582))
* **shortlink:** simplify display of short link status and improve readability ([ea4e786](https://github.com/LindemannRock/craft-shortlink-manager/commit/ea4e786aaef708d00e04ea84ef698903a0eb590e))
* **shortlink:** update redirect handling to support shared shortlink slugs and fix QR code URL generation ([18c83bb](https://github.com/LindemannRock/craft-shortlink-manager/commit/18c83bbd3079b6ef69fa558a776d52889bb109f8))


### Bug Fixes

* remove duplicate [@since](https://github.com/since) annotation in config.php ([441ff62](https://github.com/LindemannRock/craft-shortlink-manager/commit/441ff620b6eda37efbe8c08e0168ea8241513dcb))

## [5.1.6](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.1.5...v5.1.6) (2025-11-11)


### Bug Fixes

* **ip-salt-error:** enhance error message with copyable commands for generating IP hash salt ([ab26918](https://github.com/LindemannRock/craft-shortlink-manager/commit/ab26918579ac778ec2f24a8fe9433b65c8e6c2e3))

## [5.1.5](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.1.4...v5.1.5) (2025-11-11)


### Bug Fixes

* enhance QR prefix defaulting logic to support nested patterns and avoid conflicts ([48acf6f](https://github.com/LindemannRock/craft-shortlink-manager/commit/48acf6f363e68912f10758600dd16c5fd27717a7))

## [5.1.4](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.1.3...v5.1.4) (2025-11-11)


### Bug Fixes

* update QR code URL prefix to support nested patterns ([031a062](https://github.com/LindemannRock/craft-shortlink-manager/commit/031a062dc0584c51979b74e4e5ae914238a33638))

## [5.1.3](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.1.2...v5.1.3) (2025-11-11)


### Code Refactoring

* remove global enableQrCodes setting, keep per-link control ([279a7e8](https://github.com/LindemannRock/craft-shortlink-manager/commit/279a7e8b29830d8766e59dd3655ca393a4994e48))

## [5.1.2](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.1.1...v5.1.2) (2025-11-11)


### Bug Fixes

* add form validation for QR logo selection and update required status on toggle change ([3599c95](https://github.com/LindemannRock/craft-shortlink-manager/commit/3599c95cb41c7c150f05657c5ac3e2eee9bf9aa1))

## [5.1.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.1.0...v5.1.1) (2025-11-11)


### Bug Fixes

* improve handling of default QR logo ID in settings ([1e025bc](https://github.com/LindemannRock/craft-shortlink-manager/commit/1e025bcfbc266ad12d0b7b91f46028410fc63120))

## [5.1.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.0.0...v5.1.0) (2025-11-11)


### Features

* add QR templates, multi-site support, and smart-links pattern consistency ([c8e2550](https://github.com/LindemannRock/craft-shortlink-manager/commit/c8e25501b25b49c17de9d180c3b30f826b931dbb))

## 5.0.0 (2025-11-09)


### Features

* initial ShortLink Manager plugin implementation ([c2dc0d7](https://github.com/LindemannRock/craft-shortlink-manager/commit/c2dc0d7ccd2ac58d197876a6f03eb81e418fada3))
