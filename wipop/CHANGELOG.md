## [0.9.5](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.9.4...v0.9.5) (2026-04-24)

### Bug Fixes

* **release:** update versioning and metadata for plugin release ([bdcf77f](https://github.com/wipop-bbva/wipop-woocommerce/commit/bdcf77f9a64cd9f3ea66c6194a28cecf15bea98f))

## [0.9.4](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.9.3...v0.9.4) (2026-04-24)

### Bug Fixes

* **admin:** enhance password field behavior and unlock on focus/keydown ([06c8a4c](https://github.com/wipop-bbva/wipop-woocommerce/commit/06c8a4cbe82c0966b9a50e8031b446a3d7a33fa3))
* **admin:** skip validation for missing fields in fields_validator method ([c4b2c2c](https://github.com/wipop-bbva/wipop-woocommerce/commit/c4b2c2c6f2e769e3711819394dc5f4d7a6bb40b6))
* **docs:** update Terminal ID range in README and LEEME files ([a84a810](https://github.com/wipop-bbva/wipop-woocommerce/commit/a84a81074d343086e433efc28f619c70b5031037))

## [0.9.3](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.9.2...v0.9.3) (2026-04-23)

### Bug Fixes

* **release:** clear vendor directory before installing PHP dependencies ([4ca02dc](https://github.com/wipop-bbva/wipop-woocommerce/commit/4ca02dc5821057941b4b76d3a893224b19afdeca))

## [0.9.2](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.9.1...v0.9.2) (2026-04-21)

### Bug Fixes

* **translations:** update descriptions for Merchant ID and payment environment in multiple languages ([74b3d76](https://github.com/wipop-bbva/wipop-woocommerce/commit/74b3d762dafa44ee92f661117621996dd40d8731))

## [0.9.1](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.9.0...v0.9.1) (2026-04-17)

### Bug Fixes

* **order-id:** generate a new Wipop order ID per payment attempt ([f489bfb](https://github.com/wipop-bbva/wipop-woocommerce/commit/f489bfb6a4f42edbf054d020e60565450c8c47c9))
* **recurring:** register Wipop renewal identifiers for webhook lookup ([aebdfdd](https://github.com/wipop-bbva/wipop-woocommerce/commit/aebdfdd35222c9ae22576dfa5090c7142651a63d))
* **webhook:** keep Wipop attempt identifiers queryable ([7148aac](https://github.com/wipop-bbva/wipop-woocommerce/commit/7148aace1f76e4620eb39e12dbc7bd4e862701fb))

## [0.9.0](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.8.1...v0.9.0) (2026-03-17)

### Features

* **admin:** improve settings feedback and copy UX ([fa6ea2c](https://github.com/wipop-bbva/wipop-woocommerce/commit/fa6ea2cb28ddeafb81edd19732505be6fe5303f3))
* **settings:** sync terminal_id limits with Wipop SDK ([c9731f6](https://github.com/wipop-bbva/wipop-woocommerce/commit/c9731f620ade39b0dc81bb4a4a5e25758a5a8f5f))

### Bug Fixes

* **composer:** update wipop-php-client requirement to version 0.9.0 ([c50a6e3](https://github.com/wipop-bbva/wipop-woocommerce/commit/c50a6e323dbb6618601a3423db4aeee6e88db376))

## [0.8.1](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.8.0...v0.8.1) (2026-03-06)

### Bug Fixes

* **wipop:** normalize locales and inherit customerId for recurring renewals ([5fd456d](https://github.com/wipop-bbva/wipop-woocommerce/commit/5fd456dfaecc066529f7918512e21825fab5b110))

## [0.8.0](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.7.1...v0.8.0) (2026-03-04)

### Features

* **woocommerce:** add order action to stop Wipop recurring charges and update i18n ([9e8e680](https://github.com/wipop-bbva/wipop-woocommerce/commit/9e8e6806fd4c86b680e0d1cf32534eb413f2a8cf))

## [0.7.1](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.7.0...v0.7.1) (2026-02-23)

### Bug Fixes

* **i18n:** add missing translations for webhook and recurring flows ([b236ba9](https://github.com/wipop-bbva/wipop-woocommerce/commit/b236ba9e5ca81c62e45653fb4468e0f69e34f67c))

## [0.7.0](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.6.1...v0.7.0) (2026-02-23)

### Features

* **admin:** add webhook auth settings UI and credential management ([b5737d7](https://github.com/wipop-bbva/wipop-woocommerce/commit/b5737d752e964b4eb072a82624f7e935628dd76c))
* **webhook:** add auth and verification flow in webhook endpoint ([375cbdd](https://github.com/wipop-bbva/wipop-woocommerce/commit/375cbddb62e2fed01871e69f010b0b70d3387577))

### Bug Fixes

* **admin:** update description for webhook credential regeneration to reflect correct portal name ([6c57a95](https://github.com/wipop-bbva/wipop-woocommerce/commit/6c57a95cd30c37335e750106988e4e7fb41ec0a7))
* **docker:** preserve Authorization header for WordPress webhook auth ([1ea2af3](https://github.com/wipop-bbva/wipop-woocommerce/commit/1ea2af32ad444a55d5b874b47f4a21943eb7761e))
* **webhook:** correct typos in error logging messages ([294622f](https://github.com/wipop-bbva/wipop-woocommerce/commit/294622f78dfae6563a6fd11c89e104efd60135e1))
* **webhook:** ensure early return after handling verification payload ([d8081d1](https://github.com/wipop-bbva/wipop-woocommerce/commit/d8081d12f4725fc343b7c24616162bc73613077a))

## [0.6.1](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.6.0...v0.6.1) (2026-02-11)

### Bug Fixes

* **recurring:** return bool on handling of renewal order ([b58aa34](https://github.com/wipop-bbva/wipop-woocommerce/commit/b58aa344ced9b18b86a7165339f928064df5b74c))

## [0.6.0](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.5.0...v0.6.0) (2026-01-23)

### Features

* **charges:** pass customerId for logged-in users ([db6f7c5](https://github.com/wipop-bbva/wipop-woocommerce/commit/db6f7c5ae8461e8a5fd852b8bc6ad9b8b5a3c5e5))

### Bug Fixes

* **refund:** cast amount to float ([73d7362](https://github.com/wipop-bbva/wipop-woocommerce/commit/73d736298635807568207bbcbe8ad6567676bb5d))

## [0.5.0](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.4.0...v0.5.0) (2026-01-21)

### Features

* **Card:** enable save-payment + blocks tokenization support ([9190b8f](https://github.com/wipop-bbva/wipop-woocommerce/commit/9190b8f96d25b7b8daa716c929130fc2862cf083))

### Bug Fixes

* **Card Tokenization:** Fix tokenization fetch from webhook ([011df6d](https://github.com/wipop-bbva/wipop-woocommerce/commit/011df6db0e9da45d669f7c56b999ef7f7fcf1583))
* **Customer resolution:** enhance customer ID resolution from user and order metadata ([23ae3e2](https://github.com/wipop-bbva/wipop-woocommerce/commit/23ae3e24e3caf7caddc2ef7471b6d41fad73e328))
* update product type for listing payment methods as requested by API managers ([0c9bce6](https://github.com/wipop-bbva/wipop-woocommerce/commit/0c9bce67d51805b018fa2a6129d7664d7659081f))

## [0.4.0](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.3.0...v0.4.0) (2026-01-07)

### Features

* add display filters for recurring payment meta keys and values ([5bc97cf](https://github.com/wipop-bbva/wipop-woocommerce/commit/5bc97cf74a3832a96dd4177c95adde9e01f3e2db))
* add originChannel parameter to ChargeParams in ChargeRequestFactory ([0baf07b](https://github.com/wipop-bbva/wipop-woocommerce/commit/0baf07be7db5b5fb56401f413eb5e7e0b5b429c5))

### Bug Fixes

* ensure customer ID is updated in user meta when syncing order ([1959e75](https://github.com/wipop-bbva/wipop-woocommerce/commit/1959e75943fecd16bc8cb73d910e15ed9c671262))
* improve card token expiration handling in TokenManager ([6b89e93](https://github.com/wipop-bbva/wipop-woocommerce/commit/6b89e937abe22daf9b64216d10a19a4aa707be01))
* improve gateway method rendering logic outside checkout ([0d2afe1](https://github.com/wipop-bbva/wipop-woocommerce/commit/0d2afe15ea2623ac87650f57587783eddcb16887))
* update payment method type to PASARELA_PAGO in MerchantOperationsService ([c21ef23](https://github.com/wipop-bbva/wipop-woocommerce/commit/c21ef2325155ad140a7424975b50e3af5b623ff2))
* update wipop-php-client version to 0.7.0 ([6fa31b2](https://github.com/wipop-bbva/wipop-woocommerce/commit/6fa31b24a288fd8e8acea96918ed83961e94e0d2))

## [0.3.0](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.2.1...v0.3.0) (2025-12-02)

### Features

* **wp:** enable payment options within WooCommerce Blocks pages ([d6b88fa](https://github.com/wipop-bbva/wipop-woocommerce/commit/d6b88fa71922ab27acce7002d2bad4a6da76e1f2))

### Bug Fixes

* **release:** fix release pipeline permissions ([97bc9f4](https://github.com/wipop-bbva/wipop-woocommerce/commit/97bc9f4f30ef83e89953465b2ca948b120d6caee))

### Reverts

* **payment-methods:** roll back changes on local development file ([32756bb](https://github.com/wipop-bbva/wipop-woocommerce/commit/32756bb3e32bb4c5d536a9916e0c26ddda0b3883))

## [0.2.1](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.2.0...v0.2.1) (2025-12-01)

### Bug Fixes

* **localization:** recompile po files for updates translations ([0aff6f6](https://github.com/wipop-bbva/wipop-woocommerce/commit/0aff6f6bd4774d73967a08882455b37c00e374a0))
* **local:** local development load of zip library ([d2f1c8a](https://github.com/wipop-bbva/wipop-woocommerce/commit/d2f1c8a02b8b58c0fd3d59e99d6a2f02e31661c5))
* **release:** ensure release action packages all required files into the ZIP ([7209900](https://github.com/wipop-bbva/wipop-woocommerce/commit/7209900eab018b17a596c0b1c186c7597719b58d))
* **settings:** simplify wipop data verification on settings ([bbe82c7](https://github.com/wipop-bbva/wipop-woocommerce/commit/bbe82c7b0388a125ed1816c8473f220b42c86672))

## [0.2.0](https://github.com/wipop-bbva/wipop-woocommerce/compare/v0.1.0...v0.2.0) (2025-11-24)

### Features

* add wipop SDK librery to the distribution ([8bec19f](https://github.com/wipop-bbva/wipop-woocommerce/commit/8bec19ffc7216b72422486a28f2d6b57486d2cea))
* **bizum:** integrate PaymentsProcessor and update payment processing logic ([f6ae354](https://github.com/wipop-bbva/wipop-woocommerce/commit/f6ae35421ffe6d310451e273a478b46aecd61b1f))
* **card:** add WC tokenization support for Wipop card gateway ([cd48ea4](https://github.com/wipop-bbva/wipop-woocommerce/commit/cd48ea41269899461d33401a68e3d9197aa604e8))
* **card:** integrate PaymentsProcessor and update payment processing logic ([5b5326d](https://github.com/wipop-bbva/wipop-woocommerce/commit/5b5326dd3c1c4a57c0ac808f511322edca20515b))
* **charges:** implement ChargeFactory and OrderIdFactory ([9ba238c](https://github.com/wipop-bbva/wipop-woocommerce/commit/9ba238cf02c105a8cd84448708ed4a5540d767bd))
* integrate local Wipop SDK autoloader and client factory ([0bd480f](https://github.com/wipop-bbva/wipop-woocommerce/commit/0bd480f2307db9b5541c2d18451d9f0ba7cb47e9))
* **merchant-gateways:** implement merchant operations service and credential verification with ttl cache ([e9af26b](https://github.com/wipop-bbva/wipop-woocommerce/commit/e9af26b2623c11d85a7f420d60855bc968cf8cb4))
* **Order:** add support for refunds (total or partial) ([b3e365f](https://github.com/wipop-bbva/wipop-woocommerce/commit/b3e365fac312661836a94910d690a8c850e6af74))
* **OrderStatus:** add WC Order status mirror constants ([c04d531](https://github.com/wipop-bbva/wipop-woocommerce/commit/c04d53122dce38bdbabd6f0015272de2bae5a86f))
* **preauth:** add support for preauthorization operations ([9497489](https://github.com/wipop-bbva/wipop-woocommerce/commit/949748977532767782d2c0c01135f3aecfcea58d))
* **recurrent:** add support for recurrent payments ([05c43c4](https://github.com/wipop-bbva/wipop-woocommerce/commit/05c43c40ae03a7eee0076e30a28f01e9a328914d))
* **settings:** add support for terminal ID configuration in Wipop plugin ([d95970d](https://github.com/wipop-bbva/wipop-woocommerce/commit/d95970dd470a7b1646d49ee065761d5d47429ebf))
* **webhook:** implement webhook logic and sync Orders (WC status and meta) ([f13b0a2](https://github.com/wipop-bbva/wipop-woocommerce/commit/f13b0a2994ae6e2adfdfdf40bac06ab0df81622f))

### Bug Fixes

* exclude wipop-payment.zip from gitignore ([66e8f8d](https://github.com/wipop-bbva/wipop-woocommerce/commit/66e8f8df8e73db5b06e5eb7ac180c09541ac22ef))
* **release:** Fix github release permissions ([34eedb2](https://github.com/wipop-bbva/wipop-woocommerce/commit/34eedb2f1f63e859078dcd89045c2a33e0b2e60f))
* remove zip file exclusions from .gitignore ([5d12067](https://github.com/wipop-bbva/wipop-woocommerce/commit/5d1206783eda4b9e09a1b1a6a83df3cb5faa4e05))
* update Wipop library to 0.6.0 ([0068c16](https://github.com/wipop-bbva/wipop-woocommerce/commit/0068c16d264d7f59448ba05bbfbdc4f947ef15b3))
* Wipop SDK checksum ([b29b73f](https://github.com/wipop-bbva/wipop-woocommerce/commit/b29b73f9f50e34057089434d8a68dfa7c9a2d98d))

## [1.1.1](https://github.com/secture/wipop-woocommerce/compare/v1.1.0...v1.1.1) (2025-07-22)

### Bug Fixes

* **gateway:** set description field to empty string to prevent PHP deprecated warning ([#18](https://github.com/secture/wipop-woocommerce/issues/18)) ([42e36fe](https://github.com/secture/wipop-woocommerce/commit/42e36fea7b2ff8bfb45da6db73e86c9d38e84c20))

## [1.1.0](https://github.com/secture/wipop-woocommerce/compare/v1.0.2...v1.1.0) (2025-07-14)

### Features

* **admin:** enforce minimum length of 6 characters on text fields ([#11](https://github.com/secture/wipop-woocommerce/issues/11)) ([c89d304](https://github.com/secture/wipop-woocommerce/commit/c89d3040b69c576ed65f7bd9ad83027ef6ead709))

## [1.0.2](https://github.com/secture/wipop-woocommerce/compare/v1.0.1...v1.0.2) (2025-07-09)

### Bug Fixes

* **release:** package zip without root directory ([#8](https://github.com/secture/wipop-woocommerce/issues/8)) ([04e86a0](https://github.com/secture/wipop-woocommerce/commit/04e86a07824b3580d6c2e5ceec5d309b1bf0bbc2))

## [1.0.1](https://github.com/secture/wipop-woocommerce/compare/v1.0.0...v1.0.1) (2025-07-08)

### Bug Fixes

* **admin:** unify slugs for settings page to display form fields ([#6](https://github.com/secture/wipop-woocommerce/issues/6)) ([1f059cc](https://github.com/secture/wipop-woocommerce/commit/1f059cc8a389949797ba133eeaeb605e79105d5f))

## 1.0.0 (2025-07-04)

### Features

* **admin:** implement admin settings management and remove Setup class ([ab13b31](https://github.com/secture/wipop-woocommerce/commit/ab13b3115bcc8173a0af694932bcb3aab966397a))
* **gateways:** add Bizum, Card, and GPay payment gateways with initial setup ([3f2c334](https://github.com/secture/wipop-woocommerce/commit/3f2c3340215d586c49350631bb77b0d5212cbfdd))
* **i18n:**  add admin translation strings and update language files ([3d8106d](https://github.com/secture/wipop-woocommerce/commit/3d8106dafa4834f4de0503f69cec0fa544bffa62))
* **i18n:** add Catalan, English, Spanish, Basque, and Galician language support files ([06be2fb](https://github.com/secture/wipop-woocommerce/commit/06be2fb22f0761117fc192aafca148b97ce0b1f5))
* **i18n:** add initial POT file for localization support ([c0ecb35](https://github.com/secture/wipop-woocommerce/commit/c0ecb35f4cc9be6d5212c419d25ca702c2330e7b))
* **loader:** implement Loader class for payment gateways ([2bade4e](https://github.com/secture/wipop-woocommerce/commit/2bade4ed7f0ea6d891cdda13c1c29ef3162fc2a4))
* **logging:** add Logger ([9d71337](https://github.com/secture/wipop-woocommerce/commit/9d713371adb2dd0b75133e7e2909ca56f937d66f))
* **webhook:** implement webhook class for payment status notifications ([868ac78](https://github.com/secture/wipop-woocommerce/commit/868ac78ce293b27187862a5340299bd58b975813))

### Bug Fixes

* **namespace:** correct namespace in Gpay gateway ([9701443](https://github.com/secture/wipop-woocommerce/commit/9701443193c945cb491a8104537b6abca70df355))
