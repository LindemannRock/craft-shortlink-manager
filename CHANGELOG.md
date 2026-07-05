# Changelog

## [5.27.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.26.0...v5.27.0) - 2026-07-05


### Added

* **i18n:** add shortlink translations and update existing strings ([cd810b9](https://github.com/LindemannRock/craft-shortlink-manager/commit/cd810b98085ff3d4eaf09c84fa0876c9d1d60dcb))
* **settings:** add template status checks and update messages for setup guidance ([2d09128](https://github.com/LindemannRock/craft-shortlink-manager/commit/2d09128d30ec748f6432f78f4838f800607433cd))
* **setup:** add command for copying starter templates ([116dbe5](https://github.com/LindemannRock/craft-shortlink-manager/commit/116dbe5d8a139d3a477e25e926c0696894848154))
* **setup:** add setup checklist and service for readiness verification ([1547ffe](https://github.com/LindemannRock/craft-shortlink-manager/commit/1547ffe2ea56a98993d0ec33203fec9bc35a4f4b))


### Fixed

* **analytics:** analytics settings instructions for clarity ([814bef2](https://github.com/LindemannRock/craft-shortlink-manager/commit/814bef2e01c2f12b04ba632451ef453a8a9803ce))

## [5.26.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.25.0...v5.26.0) - 2026-07-03


### Added

* **analytics:** exclude disabled shortlinks from top links query ([1322236](https://github.com/LindemannRock/craft-shortlink-manager/commit/132223635c01b51a3ea196270a6a5e1fe24a684c))
* **analytics:** optimize getTopLinks query for multi-site support ([3ad57d2](https://github.com/LindemannRock/craft-shortlink-manager/commit/3ad57d253da470c2fcb830fd25ef647bb47ce291))
* **controllers:** add folder and tag validation in bulk actions ([e32c6e2](https://github.com/LindemannRock/craft-shortlink-manager/commit/e32c6e26fd375d51ca200f51ff208a22e1d938e2))
* **qrcode:** normalize color options and add fallback handling ([d40c96e](https://github.com/LindemannRock/craft-shortlink-manager/commit/d40c96e1006159adcbad2cb837deca683de2e57d))
* **settings:** add HTML escaping for shortlink names in settings templates ([ce70fad](https://github.com/LindemannRock/craft-shortlink-manager/commit/ce70fada15f02fdf8e1c2b54eba51ff39c2ca128))
* **settings:** add servd static cache availability to behavior settings ([086eff5](https://github.com/LindemannRock/craft-shortlink-manager/commit/086eff57e653d6fc85c727dc5e8a1dd8c6aab016))
* **shortlink:** cache fetched ShortLink elements for improved performance ([305a326](https://github.com/LindemannRock/craft-shortlink-manager/commit/305a326a7327675dfe6143236a31e819e4f29dbd))
* **taxonomy:** add caching for folder and tag ID lookups by slug ([6013dc7](https://github.com/LindemannRock/craft-shortlink-manager/commit/6013dc7a47cae1593223445700276b2c073fa384))
* **widgets:** add safe destination URL handling for top links ([bc23075](https://github.com/LindemannRock/craft-shortlink-manager/commit/bc23075301282917956701de097577976eae592d))


### Fixed

* **analytics:** handle total links count for empty site ID ([2476c01](https://github.com/LindemannRock/craft-shortlink-manager/commit/2476c01ac8d9a8c55e174838e6b8812b80b68885))
* **controllers:** enforce site edit permission checks in SmartlinksController ([70a61e3](https://github.com/LindemannRock/craft-shortlink-manager/commit/70a61e3350143fcf8b54a77c3df7303944f50e78))
* enforce required runtime environment for Servd static cache ([84326b8](https://github.com/LindemannRock/craft-shortlink-manager/commit/84326b817c2e0126df5cd092c86d41b6416829fa))
* escape direct redirect cache warning messages for safe output ([0830d24](https://github.com/LindemannRock/craft-shortlink-manager/commit/0830d24b96a68142c26e2ea3d88b8cfd3628f791))
* escape HTTP status tip link labels for JavaScript output ([729e497](https://github.com/LindemannRock/craft-shortlink-manager/commit/729e4975a1555bdec56e85bafafa904e489d9e90))
* escape plugin name placeholders in behavior and integrations settings ([b2d283b](https://github.com/LindemannRock/craft-shortlink-manager/commit/b2d283badc25ff0e445bf238caa8aa2c63631174))
* **gql:** sanitize expired and resolved destination URLs to prevent XSS ([d594955](https://github.com/LindemannRock/craft-shortlink-manager/commit/d594955692eb17ba54ae289edd88d8007891c9ef))
* **i18n:** correct translations across multiple locales ([ec1b58a](https://github.com/LindemannRock/craft-shortlink-manager/commit/ec1b58ac8db554ee2dc3bc6d970c9acd9df41fe8))
* **i18n:** escape plugin name placeholders in instruction texts ([c677495](https://github.com/LindemannRock/craft-shortlink-manager/commit/c67749518a06d41c79d5b95b63d9b6863cf61415))
* **import-export:** enforce site import permissions based on settings ([c445b9e](https://github.com/LindemannRock/craft-shortlink-manager/commit/c445b9ebbf5e3d835a256a79eabc38953fc9d9ec))
* **import-export:** ensure export only includes enabled sites ([efab769](https://github.com/LindemannRock/craft-shortlink-manager/commit/efab76906855843ee9c6aefda8c973efe6355d56))
* **import-export:** ensure export only includes specified site IDs ([49f4027](https://github.com/LindemannRock/craft-shortlink-manager/commit/49f4027ea0ccad80acd3c0a1a13097dca9432145))
* **import-export:** handle CSV parse errors with user-friendly messages ([81d96de](https://github.com/LindemannRock/craft-shortlink-manager/commit/81d96de952353bb4b940cf7bf02b2c3c0aaaa201))
* **permissions:** enforce site-specific edit and delete permissions ([a8cd2d5](https://github.com/LindemannRock/craft-shortlink-manager/commit/a8cd2d55ce0a1559f6cb741bd2dded2808800179))
* **settings:** update slug and QR prefix migration tips for clarity ([3889f0f](https://github.com/LindemannRock/craft-shortlink-manager/commit/3889f0f0e922aa43bbdb171c02beca4838d3f3f7))
* **widgets:** handle empty site ID in top links analytics retrieval ([eb9437b](https://github.com/LindemannRock/craft-shortlink-manager/commit/eb9437b55caa50eea60c89dcf720852d908fe98c))


### Security

* **analytics:** add attribute escaping for destination titles to prevent XSS ([b73ab46](https://github.com/LindemannRock/craft-shortlink-manager/commit/b73ab4641e654c658c759124ec9d6170e3cefae9))

## [5.25.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.24.0...v5.25.0) - 2026-07-01


### Added

* add site selection dropdown and update link status analytics ([285410b](https://github.com/LindemannRock/craft-shortlink-manager/commit/285410b0a2d30906ed43111d7b4ed80b003a0693))
* **cache:** add LocalCacheService for managing local caches ([6e1eb7e](https://github.com/LindemannRock/craft-shortlink-manager/commit/6e1eb7e61f90d0388e1ee7db796085259f5291ca))
* **i18n:** add Servd static cache messages in multiple languages ([90b7ee8](https://github.com/LindemannRock/craft-shortlink-manager/commit/90b7ee874dfb3cc9a81e8159e48b7b23fc5a5ce5))
* **servdstaticcache:** add runtime config validation for Servd cache ([8b3d20e](https://github.com/LindemannRock/craft-shortlink-manager/commit/8b3d20e4717818115f3ab3453a44716de5b0e5d0))
* **settings:** add action to purge Servd static cache for SmartLinks ([2813a7d](https://github.com/LindemannRock/craft-shortlink-manager/commit/2813a7d150d81f6a938520af0ff8e483664a455a))
* **shortlinks:** purge servd static cache on shortlink save and delete ([3f3dff5](https://github.com/LindemannRock/craft-shortlink-manager/commit/3f3dff5165475f75c7789f69cceedd9019ec02c1))


### Fixed

* fail closed for empty analytics site scopes ([5d3f9bc](https://github.com/LindemannRock/craft-shortlink-manager/commit/5d3f9bc0998ed1c31073a8b6093a9a44eae8436b))

## [5.24.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.23.1...v5.24.0) - 2026-06-30


### Added

* **services:** add FrontendService for client-side rendering helpers ([c860380](https://github.com/LindemannRock/craft-shortlink-manager/commit/c8603805ff354f7678c5ec100b026bb94da66c75))

## [5.23.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.23.0...v5.23.1) - 2026-06-30


### Fixed

* **redirects:** refine direct redirect logic for shortlinks ([eb75cdd](https://github.com/LindemannRock/craft-shortlink-manager/commit/eb75cdd16d6b1ecfdbc6761baf7d685f35580156))

## [5.23.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.22.0...v5.23.0) - 2026-06-30


### Added

* **redirects:** build public action URL for shortlinks with site handling ([857b984](https://github.com/LindemannRock/craft-shortlink-manager/commit/857b984034c8dec8aca005f255bc8c6821c95fa9))

## [5.22.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.21.1...v5.22.0) - 2026-06-29


### Added

* add commerce product element type support ([aa9ec3c](https://github.com/LindemannRock/craft-shortlink-manager/commit/aa9ec3c6eb90e424a0b35eb2a160d61d3beb1da2))
* add smoke test and compatibility check scripts ([e00411c](https://github.com/LindemannRock/craft-shortlink-manager/commit/e00411ccc5e0ce4c3033f98d3eabc5054e8f6ef1))
* **debug:** add debug banner and update console log messages in redirect template ([36d0277](https://github.com/LindemannRock/craft-shortlink-manager/commit/36d0277ccdd892e6b3a6fca84864c6a552b8db43))
* **qr:** add additional QR code generation parameters ([7417243](https://github.com/LindemannRock/craft-shortlink-manager/commit/7417243fd5f6a12e8c9a1648e4d3bfe69996adcb))
* **seomatic:** prepare SEOmatic metadata for ShortLink redirects ([8810952](https://github.com/LindemannRock/craft-shortlink-manager/commit/88109527de37081c03920ba730e510b1ebd6dc55))
* **seomatic:** refactor tracking template to use event data object ([3f032f5](https://github.com/LindemannRock/craft-shortlink-manager/commit/3f032f59986a00ee3fe73710406c13d4e90060fe))


### Fixed

* **analytics:** ensure CSRF token is properly encoded in AJAX requests ([b32da30](https://github.com/LindemannRock/craft-shortlink-manager/commit/b32da30cd95193ed03a00bd62056f5edc6352664))
* **analytics:** ensure date range is correctly encoded in export redirects ([146a19b](https://github.com/LindemannRock/craft-shortlink-manager/commit/146a19b80aa8a21f5f1d910900a742220cba235b))
* clean up QR logo overlay resources ([054cd42](https://github.com/LindemannRock/craft-shortlink-manager/commit/054cd42f77df27194ac5b4f137db2cca810cad05))
* correct display name for new shortlink button ([0fc23f5](https://github.com/LindemannRock/craft-shortlink-manager/commit/0fc23f561f63511d6fa8a3fec5cf1c91286cd660))
* correct tab label from 'Content' to 'Details' in edit template ([38d723b](https://github.com/LindemannRock/craft-shortlink-manager/commit/38d723b627cc01fe0169469939d2791dc9a8b5ce))
* ensure QR code settings use JSON encoding for defaults ([14d9bc8](https://github.com/LindemannRock/craft-shortlink-manager/commit/14d9bc8a4e655548c637d2e2f08092826d70d0d5))
* handle file_get_contents failure gracefully in cache retrieval ([09e98c3](https://github.com/LindemannRock/craft-shortlink-manager/commit/09e98c3aea0ef1c760474da0a7c8c83c6a56c102))
* **i18n:** correct Danish and Italian translations for CSV separator text ([eb94c29](https://github.com/LindemannRock/craft-shortlink-manager/commit/eb94c2905886c021a2bc70cd71963851d4ed12f5))
* **import-export:** ensure validRows count is properly JSON encoded in preview ([7540f0d](https://github.com/LindemannRock/craft-shortlink-manager/commit/7540f0defb8fb5dccdb39edc26ee10c6278092b6))
* remove slug attribute from table attributes in ShortLink element ([e28e731](https://github.com/LindemannRock/craft-shortlink-manager/commit/e28e73195c5922d868f3aa5ba606f7faf5f299c7))
* rename default plugin name to ShortLink Manager in settings table ([902890d](https://github.com/LindemannRock/craft-shortlink-manager/commit/902890d925231e5bde7486c3379dab20fb2014be))
* replace ModuleEye with PointyEye in QR code generation ([63c98f1](https://github.com/LindemannRock/craft-shortlink-manager/commit/63c98f1b58520f30e05f1547717d5fb88b4f4f6f))
* require explicit local geo defaults ([a86cff0](https://github.com/LindemannRock/craft-shortlink-manager/commit/a86cff095826de6b62df325dee510e6b0d76abde))
* **seomatic:** streamline site tracking script loading process ([6b473a9](https://github.com/LindemannRock/craft-shortlink-manager/commit/6b473a9715e1d67fefa1f072e084a21e09c4e336))
* set custom field layout values from request ([7bc3e48](https://github.com/LindemannRock/craft-shortlink-manager/commit/7bc3e485823a1af5b0e714e71594ddd392cba51c))
* **settings:** replace 'leaf' with 'pointed' in QR code eye style options ([0f7eb31](https://github.com/LindemannRock/craft-shortlink-manager/commit/0f7eb3120524d68107b9f284f9bfdbfc8688c37a))
* **shortlinks:** add site enabled check in ShortLinkField and optimize limit handling in ShortLinkResolver ([d4d61fb](https://github.com/LindemannRock/craft-shortlink-manager/commit/d4d61fb015fe00ee0a251012dd7f863188977c63))
* **shortlinks:** encode URLs for QR code generation and download ([8062eec](https://github.com/LindemannRock/craft-shortlink-manager/commit/8062eec48dc7ad5020de01d28b9b3e58d1f010b5))

## [5.21.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.21.0...v5.21.1) - 2026-06-20


### Fixed

* **i18n:** correct translations across multiple locales ([73539aa](https://github.com/LindemannRock/craft-shortlink-manager/commit/73539aa108c57a7b9c429f5245705a770a6f20b7))

## [5.21.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.20.2...v5.21.0) - 2026-06-18


### Added

* add field layout support ([e281503](https://github.com/LindemannRock/craft-shortlink-manager/commit/e281503d5de9d6f2def6699e2f240642a7c4d384))
* enrich shortlink analytics traffic exports ([bd20c75](https://github.com/LindemannRock/craft-shortlink-manager/commit/bd20c75cbeca5ea0b4d071ce256f182130bfc31b))
* **gql:** add GraphQL support ([60f2a50](https://github.com/LindemannRock/craft-shortlink-manager/commit/60f2a500d3fbaf8aa40bc57a5d8527400130730e))
* **gql:** add GraphQL support for resolving and listing shortlinks ([e514705](https://github.com/LindemannRock/craft-shortlink-manager/commit/e5147053e031b0183c4cce7bcd57818b8bca1957))
* **i18n:** add "View all analytics" translation key across locales ([f175a79](https://github.com/LindemannRock/craft-shortlink-manager/commit/f175a79d7f9c8fb1085c81f671a345d850696c18))
* **tests:** add integration tests for ShortLinkField and NativeLinkField ([dfc1e3e](https://github.com/LindemannRock/craft-shortlink-manager/commit/dfc1e3ec5ed4510939406398ce7825e152bc989f))
* **tests:** add manual CSV fixtures for testing import flow ([a5f3baf](https://github.com/LindemannRock/craft-shortlink-manager/commit/a5f3baf50733147a61d4c2f5528b3607731c1eef))


### Fixed

* **i18n:** correct OS translations in Arabic, Spanish, French, Japanese, and Dutch ([14c4fbb](https://github.com/LindemannRock/craft-shortlink-manager/commit/14c4fbba08a2c1b15c1c60fa425309b25c0ee988))
* **i18n:** correct Portuguese translation for 'saved' ([b6ac06b](https://github.com/LindemannRock/craft-shortlink-manager/commit/b6ac06b05eea4a7d64cfbec2fdc8611cb0dfc6ca))
* **i18n:** correct Swedish translations for various strings ([bdcf221](https://github.com/LindemannRock/craft-shortlink-manager/commit/bdcf22111b5fdb22c28fab026b215e98bec89099))
* **i18n:** correct translation for 'Tab' and 'Pipe' in multiple locales ([099623b](https://github.com/LindemannRock/craft-shortlink-manager/commit/099623b2eb1e0e3f6094fb62bd2af3804b4007b4))
* **i18n:** update Norwegian, Portuguese, and Swedish translations ([271cbce](https://github.com/LindemannRock/craft-shortlink-manager/commit/271cbcef8347c9d09e009b14ca16470e2cdd265f))
* **import-export:** highlight error messages in import preview table ([51ffbb1](https://github.com/LindemannRock/craft-shortlink-manager/commit/51ffbb13e941e57fe8c498b8784d6ffa9f35fb21))
* normalize shortlink import preview codes ([03ba6d7](https://github.com/LindemannRock/craft-shortlink-manager/commit/03ba6d7d7d683e7e53781822452bf34b9b9700ee))
* **qrcode:** clamp QR code size and margin to defined limits ([9a9ad0e](https://github.com/LindemannRock/craft-shortlink-manager/commit/9a9ad0e2df65a11fe874af686a247c642b097121))
* **qrcode:** correct QR code download filename format ([d4b75a4](https://github.com/LindemannRock/craft-shortlink-manager/commit/d4b75a4acd303fe53aad16801a6e5bd072f0ed98))


### Security

* block dangerous URL schemes in validation ([52d50a8](https://github.com/LindemannRock/craft-shortlink-manager/commit/52d50a8b4776654df9c92a67ec3d6d5ba6804464))

## [5.20.2](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.20.1...v5.20.2) - 2026-06-07


### Fixed

* plugin credit in edit template ([5eeaf43](https://github.com/LindemannRock/craft-shortlink-manager/commit/5eeaf43f6f60fa1bda1a0d8bb2f5c53966b371d1))

## [5.20.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.20.0...v5.20.1) - 2026-06-07


### Fixed

* move plugin credit section to edit template ([eb3bc51](https://github.com/LindemannRock/craft-shortlink-manager/commit/eb3bc51210271002055d65aca10cc34716d56c2d))

## [5.20.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.19.0...v5.20.0) - 2026-06-07


### Added

* add act-static-analysis script for CI integration ([c33dc89](https://github.com/LindemannRock/craft-shortlink-manager/commit/c33dc892537aed93d23d840f2612ed5b0aa2358f))
* add plugin credit component to edit forms ([3db94a2](https://github.com/LindemannRock/craft-shortlink-manager/commit/3db94a2b10fd167eaed7e3e1660ab802aeff6c3a))
* add plugin handle to device detection configuration ([cb8b9cd](https://github.com/LindemannRock/craft-shortlink-manager/commit/cb8b9cd88fd1b16da693acb60d2f9d4b790ce995))
* **cli:** add HelpController for cli command assistance ([35f72c2](https://github.com/LindemannRock/craft-shortlink-manager/commit/35f72c238e35827cb5dccf6e55d952354f9524bf))
* expand default date range options for analytics ([e8ad1a1](https://github.com/LindemannRock/craft-shortlink-manager/commit/e8ad1a1a5e6b612d65ab1007af22bf5e32fe20d2))
* **i18n:** add cache location and Redis configuration messages ([9fdb397](https://github.com/LindemannRock/craft-shortlink-manager/commit/9fdb397cb66ae3c58a86f84b9e9a265ed635c3da))
* **i18n:** add new translation keys for user notifications ([a05dca0](https://github.com/LindemannRock/craft-shortlink-manager/commit/a05dca0fba4511edc343cb650fffd58c779a2b47))
* **i18n:** add new translation keys for user notifications ([d52638e](https://github.com/LindemannRock/craft-shortlink-manager/commit/d52638e301bad99cb907eae2362645dba51d67c1))
* **i18n:** update analytics strings to improve clarity in translations ([c19a220](https://github.com/LindemannRock/craft-shortlink-manager/commit/c19a2201f51dd159a9257f9e37917c000e12b3e4))
* **import-export:** add docblocks for ImportExportController and related actions ([c89cf24](https://github.com/LindemannRock/craft-shortlink-manager/commit/c89cf24c5ce0d592278f822a8498afbf7dd63ccf))
* **import-export:** add field labels and mapping messages to form validation ([c711f0a](https://github.com/LindemannRock/craft-shortlink-manager/commit/c711f0a5f29a47dd1aa394bbe22ff4a13a24eeb2))
* **jobs:** schedule initial analytics cleanup job with dynamic next run time ([e49600f](https://github.com/LindemannRock/craft-shortlink-manager/commit/e49600f7ad985afb5a97576ce84606ab54a629cb))
* **settings:** add method to resolve notFoundRedirectUrl with env vars ([4ab835e](https://github.com/LindemannRock/craft-shortlink-manager/commit/4ab835e9ccfcf41fded60d7d029d5beb322b7015))
* **settings:** add new settings for QR code and analytics features ([fa55b86](https://github.com/LindemannRock/craft-shortlink-manager/commit/fa55b86aa08e0873963b522825edda37e3f4705c))
* **tests:** add integration tests for direct redirects and URL generation ([3a1ae9e](https://github.com/LindemannRock/craft-shortlink-manager/commit/3a1ae9e2d1a5ac4b4adf4e0c1cfcdf335ea66ee2))
* **tests:** add integration tests for short link types ([16be560](https://github.com/LindemannRock/craft-shortlink-manager/commit/16be560ef2d3f2a981490a60e97ef1e7272fc4bc))
* **tests:** add test for generating PNG QR code with logo overlay ([c20ca54](https://github.com/LindemannRock/craft-shortlink-manager/commit/c20ca543c023545aebf6523f6d74e72be28385e9))
* **tests:** add withSettings method to temporarily override plugin settings ([4c6471c](https://github.com/LindemannRock/craft-shortlink-manager/commit/4c6471ce82843f729e7ec4cb200683a84faf4ded))


### Fixed

* **i18n:** correct cache storage and analytics translation strings ([5690899](https://github.com/LindemannRock/craft-shortlink-manager/commit/5690899392a77cb2161815254782d9ddaf90e45a))
* **i18n:** correct Dutch translation for QR Codes ([d6c99c9](https://github.com/LindemannRock/craft-shortlink-manager/commit/d6c99c9cd46453f157cef6c77f7aace954910f02))
* **i18n:** correct German translations for analytics terms ([c25ca5a](https://github.com/LindemannRock/craft-shortlink-manager/commit/c25ca5ae8144eca89386aece2b1f03b3cb7e8e2b))
* **i18n:** correct permission error messages for import and export actions ([73bc5c2](https://github.com/LindemannRock/craft-shortlink-manager/commit/73bc5c2ec2459559ea1173c4d0e6d60a13d471eb))
* **i18n:** correct Portuguese confirmation messages for deletion actions ([cc6351c](https://github.com/LindemannRock/craft-shortlink-manager/commit/cc6351c914cf2967302f1cc8b035c1bfc6c02b0f))
* **i18n:** correct Portuguese translations for logs and status messages ([c8abaef](https://github.com/LindemannRock/craft-shortlink-manager/commit/c8abaefe00b7794ad18115d145fdde8a24887bbb))
* **i18n:** correct Portuguese translations for OS and browser terms ([4a1e358](https://github.com/LindemannRock/craft-shortlink-manager/commit/4a1e35809ca900cd48f491f06552d215988e3ede))
* **i18n:** correct punctuation in Japanese translation strings ([32bda70](https://github.com/LindemannRock/craft-shortlink-manager/commit/32bda701b21a8f94456b739de988383ab929d0f2))
* **i18n:** correct translation for CSV import and site settings ([2d7aadd](https://github.com/LindemannRock/craft-shortlink-manager/commit/2d7aaddef3eb38e97582b54889eca2445981d57f))
* **i18n:** remove 'Live' string from multiple translation files ([b6b9a10](https://github.com/LindemannRock/craft-shortlink-manager/commit/b6b9a1008314b92172d0dcd283124deb0a653cf2))
* **i18n:** remove slug display from translation strings ([b3c5c79](https://github.com/LindemannRock/craft-shortlink-manager/commit/b3c5c79dd579befe263537b293e2cebcdbc0c2cc))
* **i18n:** update error messages for CSV import validation ([0f4065a](https://github.com/LindemannRock/craft-shortlink-manager/commit/0f4065ace027bf8a6b98ae7ca19a306b7281c9c8))

## [5.19.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.18.1...v5.19.0) - 2026-05-21


### Added

* add pre-commit hook for ECS and PHPStan code quality checks ([01424bd](https://github.com/LindemannRock/craft-shortlink-manager/commit/01424bdfc9864cb1b44f24747e9c6b166f786a52))
* **analytics:** add logCategory to geo settings for tracking ([4b78e16](https://github.com/LindemannRock/craft-shortlink-manager/commit/4b78e1612a322383fe3fb684c02847e55e2212bc))
* **i18n:** add translation issue template for reporting language problems ([a49fa11](https://github.com/LindemannRock/craft-shortlink-manager/commit/a49fa1146249c0f14eaf17e33544e4fae8aaa5d7))
* **taxonomy:** implement folder and tag listing with filtering and sorting ([3507651](https://github.com/LindemannRock/craft-shortlink-manager/commit/3507651a901064f2c31f8ee71e9093b2e3271e2c))
* **tests:** add integration tests for click tracking, hit counter, and slug generation ([ab3f9ea](https://github.com/LindemannRock/craft-shortlink-manager/commit/ab3f9ea9232930949cb92946ce191209c05a004e))
* **tests:** add QrCodeServiceTest for QR code generation functionality ([487ab35](https://github.com/LindemannRock/craft-shortlink-manager/commit/487ab353ab34f9039895912f5d9aa3cb56b31367))


### Fixed

* correct phpstan include path in configuration ([48b506d](https://github.com/LindemannRock/craft-shortlink-manager/commit/48b506d1b2c94eef13d066d623addb49a3ae8618))
* **i18n:** remove untranslated plugin name and log level strings from multiple locales ([4361f3d](https://github.com/LindemannRock/craft-shortlink-manager/commit/4361f3d18ce4582d6823c332f42eb58407570cdf))
* **integrations:** prevent fatal in Link type on console runs ([57f74f4](https://github.com/LindemannRock/craft-shortlink-manager/commit/57f74f418e31fb097665875fac30165819f162be))

## [5.18.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.18.0...v5.18.1) - 2026-05-06


### Bug Fixes

* apply config overrides through shared settings helper ([5454fca](https://github.com/LindemannRock/craft-shortlink-manager/commit/5454fca8a47c951782279f6fff679b7e67717dc8))
* drop PAT requirement for release-please — use built-in GITHUB_TOKEN ([dbcc230](https://github.com/LindemannRock/craft-shortlink-manager/commit/dbcc230043c7eb6fea1df713865eea81271b82bd))
* **integrations:** update version annotations to reflect correct release versions ([a4d9765](https://github.com/LindemannRock/craft-shortlink-manager/commit/a4d97652a51b1375a0b29ddabb0b57d77f882c69))
* **translations:** correct various translation strings across locales ([6b484f6](https://github.com/LindemannRock/craft-shortlink-manager/commit/6b484f6b46697cb3d245cdcc949cfa996a12d597))
* **translations:** remove deprecated geo provider settings from multiple locales ([08db00f](https://github.com/LindemannRock/craft-shortlink-manager/commit/08db00f18adf8bcb150bc0efff825b9b4f09e2e1))
* update geo-settings inclusion to use pluginHandle instead of translationCategory ([1861c9e](https://github.com/LindemannRock/craft-shortlink-manager/commit/1861c9e94aa99936cb2b1acbd029cd40e3062c9a))

## [5.18.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.17.2...v5.18.0) - 2026-04-05


### Features

* Add 10 new language translations (FR, NL, ES, AR, IT, PT, JA, SV, DA, NO) ([2b46b18](https://github.com/LindemannRock/craft-shortlink-manager/commit/2b46b18baf5a436649e22f7e57d79eb909311453))


### Bug Fixes

* **icon:** update icon path for ShortLinkField ([ab2d277](https://github.com/LindemannRock/craft-shortlink-manager/commit/ab2d277cc5cae24816bf7e0b29f59dd578c51e8b))
* read-only settings page accessibility flag ([156a14a](https://github.com/LindemannRock/craft-shortlink-manager/commit/156a14af6247bb3cb0015fdb777f1e06493d89b7))
* update install experience text to use Craft translation ([5402356](https://github.com/LindemannRock/craft-shortlink-manager/commit/5402356e9e996056e176ff9e4144d798902c9073))

## [5.17.2](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.17.1...v5.17.2) - 2026-03-26


### Miscellaneous Chores

* **release:** remove issue permissions and skip labeling from workflow ([7557104](https://github.com/LindemannRock/craft-shortlink-manager/commit/755710435dc5bffd6cab833454bb38735bed0028))

## [5.17.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.17.0...v5.17.1) - 2026-03-26


### Bug Fixes

* **shortlink:** update QR code URL handling in ShortLink and templates ([4050920](https://github.com/LindemannRock/craft-shortlink-manager/commit/40509208bde9b7ddd0786bbba975080309a07bf9))

## [5.17.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.16.3...v5.17.0) - 2026-03-26


### Features

* **taxonomy:** add tag management functionality ([05f0f7b](https://github.com/LindemannRock/craft-shortlink-manager/commit/05f0f7b604f695e2aa69cae94bfedd0a7cfd251f))


### Bug Fixes

* **routes:** improve URL rule handling for shortlinks ([f2630ec](https://github.com/LindemannRock/craft-shortlink-manager/commit/f2630ec55e2e063caefe83487cfe41c47a5073c6))

## [5.16.3](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.16.2...v5.16.3) - 2026-03-18


### Bug Fixes

* **redirect:** change shortlink code to use slug instead of code ([619b622](https://github.com/LindemannRock/craft-shortlink-manager/commit/619b622283f5398d526cc865f42fbe0b08737243))

## [5.16.2](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.16.1...v5.16.2) - 2026-03-18


### Bug Fixes

* **config:** change default HTTP redirect code to 302 ([303b2c2](https://github.com/LindemannRock/craft-shortlink-manager/commit/303b2c2bcf6672dfb9230123dcd6bb7ec03b1d44))
* **http:** change default HTTP redirect code from 301 to 302 ([ad47d89](https://github.com/LindemannRock/craft-shortlink-manager/commit/ad47d89629d2196666685a7f4eb5c01009a9bf3a))

## [5.16.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.16.0...v5.16.1) - 2026-03-17


### Miscellaneous Chores

* **workflow:** update permissions in release-please.yml ([44ba030](https://github.com/LindemannRock/craft-shortlink-manager/commit/44ba030fb152f6c52eb67aa00f938ba97d5e2a6d))

## [5.16.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.15.1...v5.16.0) - 2026-03-17


### Features

* **analytics:** streamline IP handling in trackClick method ([e0ba4b5](https://github.com/LindemannRock/craft-shortlink-manager/commit/e0ba4b54294938db4030004c294f2c0fa5e9e1f5))

## [5.15.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.15.0...v5.15.1) - 2026-03-17


### Bug Fixes

* **analytics:** streamline click tracking and data storage ([1c1313b](https://github.com/LindemannRock/craft-shortlink-manager/commit/1c1313b8bcb3d9990cd26a94916a43f40209e6e1))

## [5.15.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.14.0...v5.15.0) - 2026-03-17


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

## [5.14.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.13.0...v5.14.0) - 2026-03-04


### Features

* add complete EN/DE translation ([d215d4d](https://github.com/LindemannRock/craft-shortlink-manager/commit/d215d4ddc385fa26e0f245d70db53c77dade0690))


### Bug Fixes

* **jobs:** implement RetryableJobInterface in CleanupAnalyticsJob ([11e4fa9](https://github.com/LindemannRock/craft-shortlink-manager/commit/11e4fa9bdf606d976528f404763f0e8bbadea523))
* **settings, qr-code:** improve translations and error messages ([f011c59](https://github.com/LindemannRock/craft-shortlink-manager/commit/f011c594cb58252750c095f9885cb6bf91951f8d))
* **settings, ShortLinkManager, ShortLink:** improve URL handling and validation ([159f2d7](https://github.com/LindemannRock/craft-shortlink-manager/commit/159f2d7db5477f7cfb5827fb402d6f1c5fac3cbf))
* **settings, validation, templates:** improve settings validation and error handling ([92fac44](https://github.com/LindemannRock/craft-shortlink-manager/commit/92fac44593b2ad23639f6d45ac46eb79c31281cd))

## [5.13.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.12.0...v5.13.0) - 2026-02-20


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

## [5.12.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.11.0...v5.12.0) - 2026-02-07


### Features

* **analytics:** add export format validation and enhance QR code generation permissions ([2289632](https://github.com/LindemannRock/craft-shortlink-manager/commit/22896325e3f738351136c49b2f36ddbfca99b834))
* **analytics:** enhance analytics data handling and sanitization ([d605cd6](https://github.com/LindemannRock/craft-shortlink-manager/commit/d605cd6efafcd2de50a1403d62d5b69f38a4ecb1))
* **analytics:** Enhance analytics functionality with user permissions and site filtering ([e1ca55b](https://github.com/LindemannRock/craft-shortlink-manager/commit/e1ca55b7e0388f8e9d582983729944bfe42f443c))

## [5.11.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.10.0...v5.11.0) - 2026-02-05


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

## [5.10.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.9.0...v5.10.0) - 2026-01-26


### Features

* replace Craft plugin calls with PluginHelper methods for consistency ([e219ba6](https://github.com/LindemannRock/craft-shortlink-manager/commit/e219ba6f839a6a36b7b27ecc52046ee438d94c14))


### Bug Fixes

* **jobs:** prevent duplicate scheduling of CleanupAnalyticsJob ([6d08934](https://github.com/LindemannRock/craft-shortlink-manager/commit/6d089345eb81778e09fd32dfa82bf00ee3a59e4d))

## [5.9.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.8.1...v5.9.0) - 2026-01-21


### Features

* Add configurable geo IP provider settings with HTTPS support ([4730d8c](https://github.com/LindemannRock/craft-shortlink-manager/commit/4730d8c535730ad8553f4097cfbfe9722144e60e))


### Bug Fixes

* swap QR Code and Behavior settings links and update heading in General Settings ([f74eccd](https://github.com/LindemannRock/craft-shortlink-manager/commit/f74eccda39aca1491fe2ef0f09ce3e9b848d9409))

## [5.8.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.8.0...v5.8.1) - 2026-01-16


### Bug Fixes

* reorganize and standardize analytics templates ([919a245](https://github.com/LindemannRock/craft-shortlink-manager/commit/919a245b1a9c7b78480db71b0eae82be6e499794))
* update cache location message to use shortlinkHelper for dynamic path ([0fd1669](https://github.com/LindemannRock/craft-shortlink-manager/commit/0fd166900606f4f948e78811aea967810abc371f))
* update filename generation to use lowerDisplayName for analytics export ([0f96df2](https://github.com/LindemannRock/craft-shortlink-manager/commit/0f96df2d29bb27b5f6166d77854cb81eee647efe))
* update hardcoded cache paths with PluginHelper for consistency ([130bd28](https://github.com/LindemannRock/craft-shortlink-manager/commit/130bd2888e418870719bac3360eee384e28929e8))
* update PluginHelper bootstrap to include download permissions for logging ([eec20fd](https://github.com/LindemannRock/craft-shortlink-manager/commit/eec20fd6b569d738d98d965fa497deb4f93533a6))

## [5.8.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.7.0...v5.8.0) - 2026-01-12


### Features

* Format cache file counts and total clicks in cache clearing buttons ([c843262](https://github.com/LindemannRock/craft-shortlink-manager/commit/c84326261e84dd14a970013b5ea2bf41a1f67b10))
* Update terminology from "Clicks" to "Interactions" and enhance link display in top links widget ([530e9aa](https://github.com/LindemannRock/craft-shortlink-manager/commit/530e9aa63f6352c9848d4a7d1f417d9c858df8c1))

## [5.7.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.6.0...v5.7.0) - 2026-01-10


### Features

* Replace custom country name retrieval with GeoHelper utility ([0dcc15b](https://github.com/LindemannRock/craft-shortlink-manager/commit/0dcc15b292ea38259b2390dc1aaeeb2a8e40132c))

## [5.6.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.5.0...v5.6.0) - 2026-01-08


### Features

* enhance documentation for custom short domains and update settings handling ([a22b7c5](https://github.com/LindemannRock/craft-shortlink-manager/commit/a22b7c58c2824b32202b3a30ae8d886e80348212))
* make element selection translatable per site for manual shortlinks ([5a1104d](https://github.com/LindemannRock/craft-shortlink-manager/commit/5a1104da21beb0cb0355c719d6aa20d9ef60ad3b))
* Refactor permissions to use grouped nested structure ([eaf3b05](https://github.com/LindemannRock/craft-shortlink-manager/commit/eaf3b0521d54de5a86a80a4b6d98f4bd278dd5b0))
* update README to include per-site translatable destinations and enhance export formats ([855d782](https://github.com/LindemannRock/craft-shortlink-manager/commit/855d782f1ce762a1bfa5b2efcf6f951237a63692))
* update Settings model methods to protected and add setDefaultQrLogoId method ([7e2690e](https://github.com/LindemannRock/craft-shortlink-manager/commit/7e2690e0bc6d0ceff5c2f5a155e2fdd34bb9892d))


### Miscellaneous Chores

* remove local composer.lock file ([705b8b8](https://github.com/LindemannRock/craft-shortlink-manager/commit/705b8b80b00e7e296ef6a0c0e44f29d9221d7ea0))

## [5.5.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.4.2...v5.5.0) - 2026-01-06


### Features

* migrate to shared base plugin ([e74da6f](https://github.com/LindemannRock/craft-shortlink-manager/commit/e74da6f349f972837e68fd2e0b22ebd80c2c67af))

## [5.4.2](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.4.1...v5.4.2) - 2026-01-05


### Bug Fixes

* add tab-content class to analytics sections for improved styling ([4b4c0ec](https://github.com/LindemannRock/craft-shortlink-manager/commit/4b4c0ec0f659c0997db2378ccbec3e5b361de8ec))

## [5.4.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.4.0...v5.4.1) - 2025-12-19


### Bug Fixes

* Refactor site selection logic in AnalyticsController for improved clarity ([60d38a3](https://github.com/LindemannRock/craft-shortlink-manager/commit/60d38a37023d9a7f6ee20b9e371014d7aa681f3d))

## [5.4.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.3.3...v5.4.0) - 2025-12-19


### Features

* Add Traffic & Devices tab with device analytics charts ([0c5fbd3](https://github.com/LindemannRock/craft-shortlink-manager/commit/0c5fbd3eb3c3a284666edc6e3fc8536b6317b664))


### Bug Fixes

* improve cache duration settings and user feedback ([f278cca](https://github.com/LindemannRock/craft-shortlink-manager/commit/f278cca6741b1ec721935e426643a81f0d12c34b))
* Rename 'Hits' label to 'Interactions' in ShortLink elements and templates ([ea90868](https://github.com/LindemannRock/craft-shortlink-manager/commit/ea9086845fdb424a3f7199357f614a66df602fcd))
* update cache label to use display name and trim whitespace in settings methods ([d134c38](https://github.com/LindemannRock/craft-shortlink-manager/commit/d134c38745f298a07c4df3a03d68fc46d6cb87b6))
* update country name mapping in analytics results ([0ece6ac](https://github.com/LindemannRock/craft-shortlink-manager/commit/0ece6ac137be3e821830b33f3981727d59acc0f2))

## [5.3.3](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.3.2...v5.3.3) - 2025-12-16


### Bug Fixes

* update icon return value in ShortLinkManagerUtility ([e4f0951](https://github.com/LindemannRock/craft-shortlink-manager/commit/e4f09519a6bb2be44809f86884b7f9593577cc39))

## [5.3.2](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.3.1...v5.3.2) - 2025-12-16


### Bug Fixes

* update time formatting in analytics dashboard to use locale settings ([cf2ad60](https://github.com/LindemannRock/craft-shortlink-manager/commit/cf2ad6025677349db175c81ac62b0c6f8e9b3e8b))

## [5.3.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.3.0...v5.3.1) - 2025-12-16


### Bug Fixes

* simplify redirect manager events to only include slug-change ([ad4cd18](https://github.com/LindemannRock/craft-shortlink-manager/commit/ad4cd1848f9f30e1a52f1e4c58759823322a94e1))

## [5.3.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.2.0...v5.3.0) - 2025-12-16


### Features

* add cache storage method configuration for different environments ([186fd37](https://github.com/LindemannRock/craft-shortlink-manager/commit/186fd37257b2ebd4afe4866ac99b4da56ef29aa8))
* add cache storage method configuration to Install migration ([4a1c9f4](https://github.com/LindemannRock/craft-shortlink-manager/commit/4a1c9f46ee4a435c3d3e848370a1bd285831fa7d))
* add Info Box component and enhance analytics display with number formatting ([6b3ee45](https://github.com/LindemannRock/craft-shortlink-manager/commit/6b3ee4514113a66b7bb49cf053a681ec481f6c70))
* enhance analytics display and timezone handling in AnalyticsController and AnalyticsService ([c636a3f](https://github.com/LindemannRock/craft-shortlink-manager/commit/c636a3fc6c75a93f2868ccfaef98f315f10ea1fa))
* implement Redis caching support and enhance cache management in ShortLinkManager ([a6429d9](https://github.com/LindemannRock/craft-shortlink-manager/commit/a6429d91eb6da1ed55ea9530905fd1f77f5cced6))
* update icon to 'link-simple.svg' and refine Redis cache display in index template ([167dd39](https://github.com/LindemannRock/craft-shortlink-manager/commit/167dd39cba300cc23cec2aeb317eba3e7fa34a4d))

## [5.2.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.1.6...v5.2.0) - 2025-12-03


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

## [5.1.6](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.1.5...v5.1.6) - 2025-11-11


### Bug Fixes

* **ip-salt-error:** enhance error message with copyable commands for generating IP hash salt ([ab26918](https://github.com/LindemannRock/craft-shortlink-manager/commit/ab26918579ac778ec2f24a8fe9433b65c8e6c2e3))

## [5.1.5](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.1.4...v5.1.5) - 2025-11-11


### Bug Fixes

* enhance QR prefix defaulting logic to support nested patterns and avoid conflicts ([48acf6f](https://github.com/LindemannRock/craft-shortlink-manager/commit/48acf6f363e68912f10758600dd16c5fd27717a7))

## [5.1.4](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.1.3...v5.1.4) - 2025-11-11


### Bug Fixes

* update QR code URL prefix to support nested patterns ([031a062](https://github.com/LindemannRock/craft-shortlink-manager/commit/031a062dc0584c51979b74e4e5ae914238a33638))

## [5.1.3](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.1.2...v5.1.3) - 2025-11-11


### Code Refactoring

* remove global enableQrCodes setting, keep per-link control ([279a7e8](https://github.com/LindemannRock/craft-shortlink-manager/commit/279a7e8b29830d8766e59dd3655ca393a4994e48))

## [5.1.2](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.1.1...v5.1.2) - 2025-11-11


### Bug Fixes

* add form validation for QR logo selection and update required status on toggle change ([3599c95](https://github.com/LindemannRock/craft-shortlink-manager/commit/3599c95cb41c7c150f05657c5ac3e2eee9bf9aa1))

## [5.1.1](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.1.0...v5.1.1) - 2025-11-11


### Bug Fixes

* improve handling of default QR logo ID in settings ([1e025bc](https://github.com/LindemannRock/craft-shortlink-manager/commit/1e025bcfbc266ad12d0b7b91f46028410fc63120))

## [5.1.0](https://github.com/LindemannRock/craft-shortlink-manager/compare/v5.0.0...v5.1.0) - 2025-11-11


### Features

* add QR templates, multi-site support, and smart-links pattern consistency ([c8e2550](https://github.com/LindemannRock/craft-shortlink-manager/commit/c8e25501b25b49c17de9d180c3b30f826b931dbb))

## 5.0.0 - 2025-11-09


### Features

* initial ShortLink Manager plugin implementation ([c2dc0d7](https://github.com/LindemannRock/craft-shortlink-manager/commit/c2dc0d7ccd2ac58d197876a6f03eb81e418fada3))
