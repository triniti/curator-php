curator-php
=============

[![Build Status](https://api.travis-ci.org/triniti/curator-php.svg)](https://travis-ci.org/triniti/curator-php)
[![Code Climate](https://codeclimate.com/github/triniti/curator-php/badges/gpa.svg)](https://codeclimate.com/github/triniti/curator-php)
[![Test Coverage](https://codeclimate.com/github/triniti/curator-php/badges/coverage.svg)](https://codeclimate.com/github/triniti/curator-php/coverage)

Php library that provides implementations for __triniti:curator__ schemas. Using this library assumes that you've already created and compiled your own pbj classes using the [Pbjc](https://github.com/gdbots/pbjc-php) and are making use of the __"triniti:curator:mixin:*"__ mixins from [triniti/schemas](https://github.com/triniti/schemas).


## Symfony Integration
Enabling these services in a Symfony app is done by importing classes and letting Symfony autoconfigure and autowire them.

__config/packages/curator.yml:__

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true
    public: false

  Triniti\Curator\:
    resource: '%kernel.project_dir%/vendor/triniti/curator/src/**/*'

```
