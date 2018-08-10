# Terminus ACME Plugin

[![CircleCI](https://circleci.com/gh/pantheon-systems/terminus-acme-plugin.svg?style=shield)](https://circleci.com/gh/pantheon-systems/terminus-acme-plugin)
[![Terminus v1.x Compatible](https://img.shields.io/badge/terminus-v1.x-green.svg)](https://github.com/pantheon-systems/terminus-secrets-plugin/tree/1.x)

Terminus commands to interact with ACME challenges. Temporary. Will be rolled into Terminus core.

## Configuration

These commands require no configuration

## Usage
* `terminus alpha:https:challenge:dns-txt <site>.<env> example.com`
 [notice] Create a DNS txt record containing:
_acme-CHALLENGE_TEXT.example.com. 300 IN TXT "CHALLENGE_TEXT"
* `terminus alpha:https:challenge:file <site>.<env>`

You must be a member of the site's team to create challenges.

## Installation
To install this plugin place it in `~/.terminus/plugins/`.

On Mac OS/Linux:
```
mkdir -p ~/.terminus/plugins
curl https://github.com/pantheon-systems/terminus-acme-plugin/archive/1.x.tar.gz -L | tar -C ~/.terminus/plugins -xvz
```

## Testing
This example project includes four testing targets:

* `composer lint`: Syntax-check all php source files.
* `composer cs`: Code-style check.
* `composer unit`: Run unit tests with phpunit
* `composer functional`: Run functional test with bats

To run all tests together, use `composer test`.

Note that prior to running the tests, you should first run:
* `composer install`
* `composer install-tools`

## Help
Run `terminus help https:challenge:dns-txt` for help.
