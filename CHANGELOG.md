# Changelog

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
