# Changelog

## [5.1.3](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.16.3...v5.1.3) (2026-03-18)


### Features

* add [@since](https://github.com/since) 5.0.0 annotation to multiple controllers, services, and models ([e7c3e39](https://github.com/LindemannRock/craft-shortlink-manager/commit/e7c3e39cb635e05275ce5c8da305af8e0750854c))
* add cache storage method configuration for different environments ([186fd37](https://github.com/LindemannRock/craft-shortlink-manager/commit/186fd37257b2ebd4afe4866ac99b4da56ef29aa8))
* add cache storage method configuration to Install migration ([4a1c9f4](https://github.com/LindemannRock/craft-shortlink-manager/commit/4a1c9f46ee4a435c3d3e848370a1bd285831fa7d))
* add complete EN/DE translation ([d215d4d](https://github.com/LindemannRock/craft-shortlink-manager/commit/d215d4ddc385fa26e0f245d70db53c77dade0690))
* Add configurable geo IP provider settings with HTTPS support ([4730d8c](https://github.com/LindemannRock/craft-shortlink-manager/commit/4730d8c535730ad8553f4097cfbfe9722144e60e))
* add expired message handling to ShortLink management ([ac732c9](https://github.com/LindemannRock/craft-shortlink-manager/commit/ac732c9727edae433ef461b0281c25896dcfc60c))
* add Info Box component and enhance analytics display with number formatting ([6b3ee45](https://github.com/LindemannRock/craft-shortlink-manager/commit/6b3ee4514113a66b7bb49cf053a681ec481f6c70))
* add installation experience details for ShortLink Manager ([de549bb](https://github.com/LindemannRock/craft-shortlink-manager/commit/de549bb49d25d19d0e71ae230d5cf3f661598722))
* add QR templates, multi-site support, and smart-links pattern consistency ([c8e2550](https://github.com/LindemannRock/craft-shortlink-manager/commit/c8e25501b25b49c17de9d180c3b30f826b931dbb))
* Add Traffic & Devices tab with device analytics charts ([0c5fbd3](https://github.com/LindemannRock/craft-shortlink-manager/commit/0c5fbd3eb3c3a284666edc6e3fc8536b6317b664))
* **analytics:** add AJAX endpoint for fetching link analytics data ([3f3b835](https://github.com/LindemannRock/craft-shortlink-manager/commit/3f3b835a820a3ad59a04731456729663c7781e6e))
* **analytics:** add build process for analytics JavaScript assets ([e07abfe](https://github.com/LindemannRock/craft-shortlink-manager/commit/e07abfe663654bf2b9d82fadab26c06861c4e305))
* **analytics:** add default location settings for local development based on environment variables ([fb6f5f3](https://github.com/LindemannRock/craft-shortlink-manager/commit/fb6f5f3379a6d6dc170ede291e5d1a05b4407009))
* **analytics:** add export format validation and enhance QR code generation permissions ([2289632](https://github.com/LindemannRock/craft-shortlink-manager/commit/22896325e3f738351136c49b2f36ddbfca99b834))
* **analytics:** add new analytics types and format recent clicks ([b57d1b8](https://github.com/LindemannRock/craft-shortlink-manager/commit/b57d1b81615bec287f74562aaac5c6818e338354))
* **analytics:** enhance analytics data handling and sanitization ([d605cd6](https://github.com/LindemannRock/craft-shortlink-manager/commit/d605cd6efafcd2de50a1403d62d5b69f38a4ecb1))
* **analytics:** enhance analytics functionality and UI ([f46f049](https://github.com/LindemannRock/craft-shortlink-manager/commit/f46f0498f78b95f91bae1d353d4d7c087649abec))
* **analytics:** Enhance analytics functionality with user permissions and site filtering ([e1ca55b](https://github.com/LindemannRock/craft-shortlink-manager/commit/e1ca55b7e0388f8e9d582983729944bfe42f443c))
* **analytics:** enhance getTopLinks and getLinkAnalytics methods to include site name in results ([1819f30](https://github.com/LindemannRock/craft-shortlink-manager/commit/1819f306229418e66592cb2d997e24dafd8e6558))
* **analytics:** streamline IP handling in trackClick method ([e0ba4b5](https://github.com/LindemannRock/craft-shortlink-manager/commit/e0ba4b54294938db4030004c294f2c0fa5e9e1f5))
* **development:** add PHPStan and EasyCodingStandard configurations ([1a40413](https://github.com/LindemannRock/craft-shortlink-manager/commit/1a404134d984c5c95b7c01374d9be2d8569a1402))
* enhance analytics display and timezone handling in AnalyticsController and AnalyticsService ([c636a3f](https://github.com/LindemannRock/craft-shortlink-manager/commit/c636a3fc6c75a93f2868ccfaef98f315f10ea1fa))
* enhance documentation for custom short domains and update settings handling ([a22b7c5](https://github.com/LindemannRock/craft-shortlink-manager/commit/a22b7c58c2824b32202b3a30ae8d886e80348212))
* enhance short link status display with additional states ([f71a46b](https://github.com/LindemannRock/craft-shortlink-manager/commit/f71a46bd813dcf129107b145f909f688a5677927))
* **expiration:** add site-specific expired message handling and remove expiration toggle ([d1ef24f](https://github.com/LindemannRock/craft-shortlink-manager/commit/d1ef24f5ab54e885d78776458dbc96bd0a044052))
* Format cache file counts and total clicks in cache clearing buttons ([c843262](https://github.com/LindemannRock/craft-shortlink-manager/commit/c84326261e84dd14a970013b5ea2bf41a1f67b10))
* implement Redis caching support and enhance cache management in ShortLinkManager ([a6429d9](https://github.com/LindemannRock/craft-shortlink-manager/commit/a6429d91eb6da1ed55ea9530905fd1f77f5cced6))
* **import/export:** add import/export functionality with history tracking ([5efef73](https://github.com/LindemannRock/craft-shortlink-manager/commit/5efef73045b88251128cf947d754271535bb38c0))
* **import/export:** add new fields for QR code customization and query params ([d240c96](https://github.com/LindemannRock/craft-shortlink-manager/commit/d240c96f6a41956e8ab59be664ff22458fecc26f))
* **import/export:** add permission check for import/export functionality ([beb937a](https://github.com/LindemannRock/craft-shortlink-manager/commit/beb937a4125810831ce61d21b34d360254647785))
* **import/export:** enhance import/export functionality with date fields ([0223c1b](https://github.com/LindemannRock/craft-shortlink-manager/commit/0223c1b3c258e9ffd73e2f150ad8425bc1fa96ca))
* **import/export:** update CSV export and import fields for postDate ([60962a3](https://github.com/LindemannRock/craft-shortlink-manager/commit/60962a36690243fb093c12422ad7bab422178c43))
* initial ShortLink Manager plugin implementation ([c2dc0d7](https://github.com/LindemannRock/craft-shortlink-manager/commit/c2dc0d7ccd2ac58d197876a6f03eb81e418fada3))
* **layouts:** add new layout for control panel and update existing templates to use it ([da78d85](https://github.com/LindemannRock/craft-shortlink-manager/commit/da78d85e38b36cf80bcfc32b6bbfe95c2ca2b939))
* make element selection translatable per site for manual shortlinks ([5a1104d](https://github.com/LindemannRock/craft-shortlink-manager/commit/5a1104da21beb0cb0355c719d6aa20d9ef60ad3b))
* migrate to shared base plugin ([e74da6f](https://github.com/LindemannRock/craft-shortlink-manager/commit/e74da6f349f972837e68fd2e0b22ebd80c2c67af))
* **qr-code:** update QR code logo handling and improve sidebar display ([34a7776](https://github.com/LindemannRock/craft-shortlink-manager/commit/34a7776f05c39e7ec698b81a2fd97edeee3b0ffb))
* **records:** add Folder, Tag, and ShortLinkTag records ([82f0c62](https://github.com/LindemannRock/craft-shortlink-manager/commit/82f0c622fbd2a72dcc4c95d6679f8f0f213c9e75))
* **redirect:** implement 404 handling through Redirect Manager integration ([b5978df](https://github.com/LindemannRock/craft-shortlink-manager/commit/b5978dfbb3239839026340f5c9fc114f3ad12223))
* Refactor permissions to use grouped nested structure ([eaf3b05](https://github.com/LindemannRock/craft-shortlink-manager/commit/eaf3b0521d54de5a86a80a4b6d98f4bd278dd5b0))
* replace Craft plugin calls with PluginHelper methods for consistency ([e219ba6](https://github.com/LindemannRock/craft-shortlink-manager/commit/e219ba6f839a6a36b7b27ecc52046ee438d94c14))
* Replace custom country name retrieval with GeoHelper utility ([0dcc15b](https://github.com/LindemannRock/craft-shortlink-manager/commit/0dcc15b292ea38259b2390dc1aaeeb2a8e40132c))
* **services:** implement TaxonomyService for folder and tag management ([82f0c62](https://github.com/LindemannRock/craft-shortlink-manager/commit/82f0c622fbd2a72dcc4c95d6679f8f0f213c9e75))
* **settings:** add passQueryParams option for query parameter handling ([d13589d](https://github.com/LindemannRock/craft-shortlink-manager/commit/d13589d15c5436c0f174bfea67cd6b36bdac975c))
* **settings:** add usePrefix option for shortlink URL generation ([d64b19d](https://github.com/LindemannRock/craft-shortlink-manager/commit/d64b19de727f43cf908073d5f1bf52cbdf412291))
* **shortlink-field:** add Link field integration and enhance settings UI ([42672b0](https://github.com/LindemannRock/craft-shortlink-manager/commit/42672b06131ecd9447ff81990c50297ba40fc1c5))
* **shortlink-status:** add pending status and update query handling for pending links ([e0817fd](https://github.com/LindemannRock/craft-shortlink-manager/commit/e0817fdc3c1064b085255df868617c27710f7582))
* **shortlink:** add directRedirect setting for server-side redirects ([91eb418](https://github.com/LindemannRock/craft-shortlink-manager/commit/91eb4187e3f6f6f0662139738b4b314e3ad29557))
* **shortlink:** add site-aware shortlink routes and base URL settings ([34c3c0f](https://github.com/LindemannRock/craft-shortlink-manager/commit/34c3c0fd67d104319131ea63c30f345193706f79))
* **shortlink:** simplify display of short link status and improve readability ([ea4e786](https://github.com/LindemannRock/craft-shortlink-manager/commit/ea4e786aaef708d00e04ea84ef698903a0eb590e))
* **shortlink:** update redirect handling to support shared shortlink slugs and fix QR code URL generation ([18c83bb](https://github.com/LindemannRock/craft-shortlink-manager/commit/18c83bbd3079b6ef69fa558a776d52889bb109f8))
* **templates:** add folder management UI ([82f0c62](https://github.com/LindemannRock/craft-shortlink-manager/commit/82f0c622fbd2a72dcc4c95d6679f8f0f213c9e75))
* **templates:** enhance import/export functionality with folders and tags ([82f0c62](https://github.com/LindemannRock/craft-shortlink-manager/commit/82f0c622fbd2a72dcc4c95d6679f8f0f213c9e75))
* update icon to 'link-simple.svg' and refine Redis cache display in index template ([167dd39](https://github.com/LindemannRock/craft-shortlink-manager/commit/167dd39cba300cc23cec2aeb317eba3e7fa34a4d))
* update README to include per-site translatable destinations and enhance export formats ([855d782](https://github.com/LindemannRock/craft-shortlink-manager/commit/855d782f1ce762a1bfa5b2efcf6f951237a63692))
* update Settings model methods to protected and add setDefaultQrLogoId method ([7e2690e](https://github.com/LindemannRock/craft-shortlink-manager/commit/7e2690e0bc6d0ceff5c2f5a155e2fdd34bb9892d))
* Update terminology from "Clicks" to "Interactions" and enhance link display in top links widget ([530e9aa](https://github.com/LindemannRock/craft-shortlink-manager/commit/530e9aa63f6352c9848d4a7d1f417d9c858df8c1))


### Bug Fixes

* add form validation for QR logo selection and update required status on toggle change ([3599c95](https://github.com/LindemannRock/craft-shortlink-manager/commit/3599c95cb41c7c150f05657c5ac3e2eee9bf9aa1))
* add tab-content class to analytics sections for improved styling ([4b4c0ec](https://github.com/LindemannRock/craft-shortlink-manager/commit/4b4c0ec0f659c0997db2378ccbec3e5b361de8ec))
* **AnalyticsController, QrCodeController, SettingsController:** enforce permissions for analytics and cache actions ([2530b4b](https://github.com/LindemannRock/craft-shortlink-manager/commit/2530b4b9b0e994a364f017c5d5c5e3afab2c8c0c))
* **analytics:** streamline click tracking and data storage ([1c1313b](https://github.com/LindemannRock/craft-shortlink-manager/commit/1c1313b8bcb3d9990cd26a94916a43f40209e6e1))
* **config:** change default HTTP redirect code to 302 ([303b2c2](https://github.com/LindemannRock/craft-shortlink-manager/commit/303b2c2bcf6672dfb9230123dcd6bb7ec03b1d44))
* enhance QR prefix defaulting logic to support nested patterns and avoid conflicts ([48acf6f](https://github.com/LindemannRock/craft-shortlink-manager/commit/48acf6f363e68912f10758600dd16c5fd27717a7))
* **http:** change default HTTP redirect code from 301 to 302 ([ad47d89](https://github.com/LindemannRock/craft-shortlink-manager/commit/ad47d89629d2196666685a7f4eb5c01009a9bf3a))
* improve cache duration settings and user feedback ([f278cca](https://github.com/LindemannRock/craft-shortlink-manager/commit/f278cca6741b1ec721935e426643a81f0d12c34b))
* improve handling of default QR logo ID in settings ([1e025bc](https://github.com/LindemannRock/craft-shortlink-manager/commit/1e025bcfbc266ad12d0b7b91f46028410fc63120))
* **ip-salt-error:** enhance error message with copyable commands for generating IP hash salt ([ab26918](https://github.com/LindemannRock/craft-shortlink-manager/commit/ab26918579ac778ec2f24a8fe9433b65c8e6c2e3))
* **jobs:** implement RetryableJobInterface in CleanupAnalyticsJob ([11e4fa9](https://github.com/LindemannRock/craft-shortlink-manager/commit/11e4fa9bdf606d976528f404763f0e8bbadea523))
* **jobs:** prevent duplicate scheduling of CleanupAnalyticsJob ([6d08934](https://github.com/LindemannRock/craft-shortlink-manager/commit/6d089345eb81778e09fd32dfa82bf00ee3a59e4d))
* **migrations:** add slugPrefix and adjust usePrefix column position ([124ec89](https://github.com/LindemannRock/craft-shortlink-manager/commit/124ec89ab85e3027d549ab3e023ab234cb50caea))
* **redirect:** change shortlink code to use slug instead of code ([619b622](https://github.com/LindemannRock/craft-shortlink-manager/commit/619b622283f5398d526cc865f42fbe0b08737243))
* **RedirectController:** handle malformed URLs and protocol-relative links ([12e0d58](https://github.com/LindemannRock/craft-shortlink-manager/commit/12e0d586b3707b06b6bc645ae7da22cde0fbfeef))
* Refactor site selection logic in AnalyticsController for improved clarity ([60d38a3](https://github.com/LindemannRock/craft-shortlink-manager/commit/60d38a37023d9a7f6ee20b9e371014d7aa681f3d))
* remove duplicate [@since](https://github.com/since) annotation in config.php ([441ff62](https://github.com/LindemannRock/craft-shortlink-manager/commit/441ff620b6eda37efbe8c08e0168ea8241513dcb))
* Rename 'Hits' label to 'Interactions' in ShortLink elements and templates ([ea90868](https://github.com/LindemannRock/craft-shortlink-manager/commit/ea9086845fdb424a3f7199357f614a66df602fcd))
* reorganize and standardize analytics templates ([919a245](https://github.com/LindemannRock/craft-shortlink-manager/commit/919a245b1a9c7b78480db71b0eae82be6e499794))
* **settings, qr-code:** improve translations and error messages ([f011c59](https://github.com/LindemannRock/craft-shortlink-manager/commit/f011c594cb58252750c095f9885cb6bf91951f8d))
* **settings, ShortLinkManager, ShortLink:** improve URL handling and validation ([159f2d7](https://github.com/LindemannRock/craft-shortlink-manager/commit/159f2d7db5477f7cfb5827fb402d6f1c5fac3cbf))
* **settings, validation, templates:** improve settings validation and error handling ([92fac44](https://github.com/LindemannRock/craft-shortlink-manager/commit/92fac44593b2ad23639f6d45ac46eb79c31281cd))
* **settings:** remove redundant submit button from settings forms ([5aac23d](https://github.com/LindemannRock/craft-shortlink-manager/commit/5aac23d5cea8c9309b582947d916db4f9a610582))
* **settings:** validate shortlink base URL to prevent spaces ([3bcdc85](https://github.com/LindemannRock/craft-shortlink-manager/commit/3bcdc85bad62ec0bbe88305672f15d4a11be69d6))
* **shortlink:** handle existing links switched from vanity to code ([1f25853](https://github.com/LindemannRock/craft-shortlink-manager/commit/1f25853ca1ce47d48ed09cba2e02681691ebd810))
* **ShortLinkManager:** update [@since](https://github.com/since) annotation for getCpSections method to 5.11.0 ([3731baf](https://github.com/LindemannRock/craft-shortlink-manager/commit/3731baf369ba5d93050c5f642d436129dbe16143))
* simplify redirect manager events to only include slug-change ([ad4cd18](https://github.com/LindemannRock/craft-shortlink-manager/commit/ad4cd1848f9f30e1a52f1e4c58759823322a94e1))
* swap QR Code and Behavior settings links and update heading in General Settings ([f74eccd](https://github.com/LindemannRock/craft-shortlink-manager/commit/f74eccda39aca1491fe2ef0f09ce3e9b848d9409))
* update cache label to use display name and trim whitespace in settings methods ([d134c38](https://github.com/LindemannRock/craft-shortlink-manager/commit/d134c38745f298a07c4df3a03d68fc46d6cb87b6))
* update cache location message to use shortlinkHelper for dynamic path ([0fd1669](https://github.com/LindemannRock/craft-shortlink-manager/commit/0fd166900606f4f948e78811aea967810abc371f))
* update country name mapping in analytics results ([0ece6ac](https://github.com/LindemannRock/craft-shortlink-manager/commit/0ece6ac137be3e821830b33f3981727d59acc0f2))
* update filename generation to use lowerDisplayName for analytics export ([0f96df2](https://github.com/LindemannRock/craft-shortlink-manager/commit/0f96df2d29bb27b5f6166d77854cb81eee647efe))
* update hardcoded cache paths with PluginHelper for consistency ([130bd28](https://github.com/LindemannRock/craft-shortlink-manager/commit/130bd2888e418870719bac3360eee384e28929e8))
* update icon return value in ShortLinkManagerUtility ([e4f0951](https://github.com/LindemannRock/craft-shortlink-manager/commit/e4f09519a6bb2be44809f86884b7f9593577cc39))
* update PluginHelper bootstrap to include download permissions for logging ([eec20fd](https://github.com/LindemannRock/craft-shortlink-manager/commit/eec20fd6b569d738d98d965fa497deb4f93533a6))
* update QR code URL prefix to support nested patterns ([031a062](https://github.com/LindemannRock/craft-shortlink-manager/commit/031a062dc0584c51979b74e4e5ae914238a33638))
* update time formatting in analytics dashboard to use locale settings ([cf2ad60](https://github.com/LindemannRock/craft-shortlink-manager/commit/cf2ad6025677349db175c81ac62b0c6f8e9b3e8b))
* validate analytics type parameter and replace getenv() ([722d1ea](https://github.com/LindemannRock/craft-shortlink-manager/commit/722d1eade54b9567e181016adef2b5879a66deb0))


### Miscellaneous Chores

* **.gitignore:** clean up ignored files and add internal directory ([ec51c42](https://github.com/LindemannRock/craft-shortlink-manager/commit/ec51c422b49f8e4f1f00a53fdea54039dfa329b5))
* add .gitattributes with export-ignore for Packagist distribution ([fc993cd](https://github.com/LindemannRock/craft-shortlink-manager/commit/fc993cdfc60ddaf773331feb46a1914fce77dcb6))
* **dependencies:** Remove matomo/device-detector from composer.json ([e22887f](https://github.com/LindemannRock/craft-shortlink-manager/commit/e22887fc299ccdb03b2790e801b97baaee989e7a))
* **main:** release 5.0.0 ([12614f8](https://github.com/LindemannRock/craft-shortlink-manager/commit/12614f8ee5667fa71fb97db102e58263907cb531))
* **main:** release 5.0.0 ([15c5ceb](https://github.com/LindemannRock/craft-shortlink-manager/commit/15c5ceb4fab4b0b058d70374317bf3b057a777fc))
* **main:** release 5.1.0 ([0661e4a](https://github.com/LindemannRock/craft-shortlink-manager/commit/0661e4a83a2ae5da93cd5c73af4506a409d4c822))
* **main:** release 5.1.0 ([dfc787d](https://github.com/LindemannRock/craft-shortlink-manager/commit/dfc787df0a434d9f1598d5c2d103bd7c090e7127))
* **main:** release 5.1.1 ([d856c9f](https://github.com/LindemannRock/craft-shortlink-manager/commit/d856c9f437c78be5aa15e355d2c36e38f5a15273))
* **main:** release 5.1.1 ([255cbb4](https://github.com/LindemannRock/craft-shortlink-manager/commit/255cbb474ae70c0f9329877789aab96891d002e5))
* **main:** release 5.1.2 ([1d887ac](https://github.com/LindemannRock/craft-shortlink-manager/commit/1d887acd6eabd7ead273890397a7f2f6cce711fd))
* **main:** release 5.1.2 ([f4afeeb](https://github.com/LindemannRock/craft-shortlink-manager/commit/f4afeeb4e3512a9c55395c0a951e72f7f4597b59))
* **main:** release 5.1.3 ([8d295bb](https://github.com/LindemannRock/craft-shortlink-manager/commit/8d295bba8c5cfc6d486e139c56b192091cd9cc9e))
* **main:** release 5.1.3 ([36535b6](https://github.com/LindemannRock/craft-shortlink-manager/commit/36535b618ca2de8a12113d5d420b2e534e523d42))
* **main:** release 5.1.4 ([a6968a9](https://github.com/LindemannRock/craft-shortlink-manager/commit/a6968a91fe602d030fd7c90c8305eda2a358d015))
* **main:** release 5.1.4 ([411d6c9](https://github.com/LindemannRock/craft-shortlink-manager/commit/411d6c9b85fc06b662de2b98a8df2b43ca061fcf))
* **main:** release 5.1.5 ([7efa35d](https://github.com/LindemannRock/craft-shortlink-manager/commit/7efa35d1f20590ef7e196ab506952bb2f91b1016))
* **main:** release 5.1.5 ([3558caa](https://github.com/LindemannRock/craft-shortlink-manager/commit/3558caafd8b0cff3066201e4b95d0b18e1abf47b))
* **main:** release 5.1.6 ([28861a0](https://github.com/LindemannRock/craft-shortlink-manager/commit/28861a047d707ff866f1d967956eed99c70b5597))
* **main:** release 5.1.6 ([cb7bf56](https://github.com/LindemannRock/craft-shortlink-manager/commit/cb7bf564d708ee85e92a196e6f4f891f237fa419))
* **main:** release 5.10.0 ([a0dafcf](https://github.com/LindemannRock/craft-shortlink-manager/commit/a0dafcf92998e42878826f875effd3d4338f5580))
* **main:** release 5.10.0 ([2f32c0b](https://github.com/LindemannRock/craft-shortlink-manager/commit/2f32c0b759543779718f38b65c5a3c50f357bf7a))
* **main:** release 5.11.0 ([8ae2950](https://github.com/LindemannRock/craft-shortlink-manager/commit/8ae295056acb5b90b180bda620294f02f4d97a1c))
* **main:** release 5.11.0 ([587e5d8](https://github.com/LindemannRock/craft-shortlink-manager/commit/587e5d834c999dee4b255b87d306e946b7b8d979))
* **main:** release 5.12.0 ([744d795](https://github.com/LindemannRock/craft-shortlink-manager/commit/744d795451ae1408942129e4d19f0044e192e01a))
* **main:** release 5.12.0 ([c9bd3ee](https://github.com/LindemannRock/craft-shortlink-manager/commit/c9bd3ee4851873ddaaa80026ca31079313f3a07f))
* **main:** release 5.13.0 ([e4514f8](https://github.com/LindemannRock/craft-shortlink-manager/commit/e4514f8bcd11d2c624e9d975a09a4796477d29ea))
* **main:** release 5.13.0 ([7b81849](https://github.com/LindemannRock/craft-shortlink-manager/commit/7b81849b540398383983a9c61de776e18ace28fc))
* **main:** release 5.14.0 ([6345cd2](https://github.com/LindemannRock/craft-shortlink-manager/commit/6345cd2833c6c064921b64d1b0edbaf42f237fcf))
* **main:** release 5.14.0 ([2f18850](https://github.com/LindemannRock/craft-shortlink-manager/commit/2f1885039be6f615fbda33b04a4090755ddff4da))
* **main:** release 5.15.0 ([bbf6813](https://github.com/LindemannRock/craft-shortlink-manager/commit/bbf6813318f58e0f8e1d2b8df4fb81351ba81c9e))
* **main:** release 5.15.0 ([ffb23ce](https://github.com/LindemannRock/craft-shortlink-manager/commit/ffb23ceff0c22b1d55ed813d44bdd381fb97c25a))
* **main:** release 5.15.1 ([8a44122](https://github.com/LindemannRock/craft-shortlink-manager/commit/8a441224d61ab50cc47034c55606c5bc86ffc6d0))
* **main:** release 5.15.1 ([70f0b20](https://github.com/LindemannRock/craft-shortlink-manager/commit/70f0b20248dcd5e172c2d63731cde90abee67df2))
* **main:** release 5.16.0 ([f3551f6](https://github.com/LindemannRock/craft-shortlink-manager/commit/f3551f6ce80e7a41bd134f9d6d130a356773cfcc))
* **main:** release 5.16.0 ([952b54e](https://github.com/LindemannRock/craft-shortlink-manager/commit/952b54e2e5ce39998aef2d31ccd84e804c21b373))
* **main:** release 5.16.1 ([2894d98](https://github.com/LindemannRock/craft-shortlink-manager/commit/2894d9847451c4e68dfedd54bd64bbca9641785c))
* **main:** release 5.16.1 ([7b84e45](https://github.com/LindemannRock/craft-shortlink-manager/commit/7b84e45c8775e50f686c060a53bde1d30fccb741))
* **main:** release 5.16.2 ([643415b](https://github.com/LindemannRock/craft-shortlink-manager/commit/643415bb4a014bca9d533a10d2609ad3c215e19c))
* **main:** release 5.16.2 ([01180ec](https://github.com/LindemannRock/craft-shortlink-manager/commit/01180ecdcee217df066abbd28a5129d1677b3f0c))
* **main:** release 5.16.3 ([bd6552c](https://github.com/LindemannRock/craft-shortlink-manager/commit/bd6552cb18b5d4bd8b9d4cd8e32ded6c3967d500))
* **main:** release 5.16.3 ([fd96058](https://github.com/LindemannRock/craft-shortlink-manager/commit/fd960585c5ea0ef4eed828a5697a53c261c138b3))
* **main:** release 5.2.0 ([e43b063](https://github.com/LindemannRock/craft-shortlink-manager/commit/e43b063d6bb3d770c429627a168db25704c83f3d))
* **main:** release 5.2.0 ([7be59f8](https://github.com/LindemannRock/craft-shortlink-manager/commit/7be59f8b4c53c835bf2c54e7307d22ad24e69bd3))
* **main:** release 5.3.0 ([55dd958](https://github.com/LindemannRock/craft-shortlink-manager/commit/55dd95872da570952df8413eadb2ff872de0b04e))
* **main:** release 5.3.0 ([8d7dae6](https://github.com/LindemannRock/craft-shortlink-manager/commit/8d7dae67e2de3a668530b558fa5adb01930de96c))
* **main:** release 5.3.1 ([785f6c4](https://github.com/LindemannRock/craft-shortlink-manager/commit/785f6c4c1ae52837ad9665f515e6647b29e8dd67))
* **main:** release 5.3.1 ([c26cd28](https://github.com/LindemannRock/craft-shortlink-manager/commit/c26cd28548530c91dbb073e75caa608dc4256535))
* **main:** release 5.3.2 ([2a5d77c](https://github.com/LindemannRock/craft-shortlink-manager/commit/2a5d77cb7fdab03fd945eb1f4bdacd25e14e9b1b))
* **main:** release 5.3.2 ([49cfa80](https://github.com/LindemannRock/craft-shortlink-manager/commit/49cfa803432b68d8c8fd644fcd17c37aee1d7854))
* **main:** release 5.3.3 ([316385d](https://github.com/LindemannRock/craft-shortlink-manager/commit/316385dfb667cb199cef024588fb0dd0288f9ee1))
* **main:** release 5.3.3 ([545da5d](https://github.com/LindemannRock/craft-shortlink-manager/commit/545da5dbf677d2c294ef82558b92d22b74c43e40))
* **main:** release 5.4.0 ([78391bd](https://github.com/LindemannRock/craft-shortlink-manager/commit/78391bd1beebfc5102a67f7c8e1dc4c260e05e95))
* **main:** release 5.4.0 ([445f85e](https://github.com/LindemannRock/craft-shortlink-manager/commit/445f85ee72a04da3f0f0190246592d3e44d28e9b))
* **main:** release 5.4.1 ([8ac4a9e](https://github.com/LindemannRock/craft-shortlink-manager/commit/8ac4a9e60907e5fa616a69f80f6b179cc79ce9af))
* **main:** release 5.4.1 ([98577e2](https://github.com/LindemannRock/craft-shortlink-manager/commit/98577e23028ac4d932094b1e34fd7ddc604dc8a7))
* **main:** release 5.4.2 ([903baa9](https://github.com/LindemannRock/craft-shortlink-manager/commit/903baa9acc5efabf435432b0a8e69155b8ec489c))
* **main:** release 5.4.2 ([fa61315](https://github.com/LindemannRock/craft-shortlink-manager/commit/fa61315a6ce3b390c7ce6df310e631d3f14ef87f))
* **main:** release 5.5.0 ([ba39c19](https://github.com/LindemannRock/craft-shortlink-manager/commit/ba39c193849d43534b4956e0c828ff63c7db27f1))
* **main:** release 5.5.0 ([4b1d9d7](https://github.com/LindemannRock/craft-shortlink-manager/commit/4b1d9d70fe8623287668752348b83d01c3e65db9))
* **main:** release 5.6.0 ([5b90e15](https://github.com/LindemannRock/craft-shortlink-manager/commit/5b90e15550f56f5aba2d30ef2e7e856de3cfff39))
* **main:** release 5.6.0 ([56f6103](https://github.com/LindemannRock/craft-shortlink-manager/commit/56f610373471f69a09d5ba8c4e355b8b879b7c3f))
* **main:** release 5.7.0 ([9d09c7f](https://github.com/LindemannRock/craft-shortlink-manager/commit/9d09c7f91e033fcd892309f93298a3557781c1a9))
* **main:** release 5.7.0 ([5f19087](https://github.com/LindemannRock/craft-shortlink-manager/commit/5f1908712202795cdaf0cb91d54de612a2be59d4))
* **main:** release 5.8.0 ([5b4281c](https://github.com/LindemannRock/craft-shortlink-manager/commit/5b4281caa2f5d353b87650ce142f9a74115c0646))
* **main:** release 5.8.0 ([4c71b95](https://github.com/LindemannRock/craft-shortlink-manager/commit/4c71b95a80f4d507f4c8b1fc9c10ce9147b3a4e0))
* **main:** release 5.8.1 ([6ad8ddb](https://github.com/LindemannRock/craft-shortlink-manager/commit/6ad8ddbc59d3c854e2f0c8b88481787baf4e88bc))
* **main:** release 5.8.1 ([147e6bb](https://github.com/LindemannRock/craft-shortlink-manager/commit/147e6bb11cd849ee4db016fca83f4ceef9306a25))
* **main:** release 5.9.0 ([bf73637](https://github.com/LindemannRock/craft-shortlink-manager/commit/bf73637052a82ee63603977f7c65ded805fdbb4e))
* **main:** release 5.9.0 ([6da97e0](https://github.com/LindemannRock/craft-shortlink-manager/commit/6da97e0a97ebc199620822768fe0281f14910b32))
* remove local composer.lock file ([705b8b8](https://github.com/LindemannRock/craft-shortlink-manager/commit/705b8b80b00e7e296ef6a0c0e44f29d9221d7ea0))
* switch to Craft License for commercial release ([0f8f8e5](https://github.com/LindemannRock/craft-shortlink-manager/commit/0f8f8e587071ca55427888c2f79085dd1be2b9cc))
* update package-lock.json and package.json for dependency management ([c3bc52d](https://github.com/LindemannRock/craft-shortlink-manager/commit/c3bc52d117cb0566c93788cc670d106616b4a54b))
* update package.json to include author and company information ([0c0d2da](https://github.com/LindemannRock/craft-shortlink-manager/commit/0c0d2da50c875247c1157df2cf2ab84711bb7e6c))
* **workflow:** update permissions in release-please.yml ([44ba030](https://github.com/LindemannRock/craft-shortlink-manager/commit/44ba030fb152f6c52eb67aa00f938ba97d5e2a6d))


### Code Refactoring

* remove global enableQrCodes setting, keep per-link control ([279a7e8](https://github.com/LindemannRock/craft-shortlink-manager/commit/279a7e8b29830d8766e59dd3655ca393a4994e48))

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
