# Terminus ACME Plugin

[![Terminus v1.x Compatible](https://img.shields.io/badge/terminus-v1.x-green.svg)](https://github.com/pantheon-systems/terminus)
[![Terminus v2.x Compatible](https://img.shields.io/badge/terminus-v2.x-green.svg)](https://github.com/pantheon-systems/terminus)

Terminus commands to interact with ACME domain ownership validation challenges.

You can use these commands to allow Pantheon to obtain an HTTPS certificate for your domain
before you go live on Pantheon.

## Configuration

These commands require no configuration.

## Usage
### 1. Obtain an ACME challenge to prove domain ownership
Choose to provision a DNS txt record or to serve a file from your existing webserver to prove you own the domain.

#### Using a DNS TXT record:
* `terminus alpha:https:challenge:dns-txt <site>.<env> example.com`
```
 [notice] Create a DNS txt record containing:
_acme-challenge.example.com. 300 IN TXT "CHALLENGE_TEXT"

 [notice] After this is complete, run terminus acme-txt-verify <site>.<env> example.com
```

This command also supports the `--format` and `--fields` options to assist in automating
your workflows with Terminus.

#### Using a file on your existing webserver:
* `terminus alpha:https:challenge:file <site>.<env> example.com`
```
 [notice] Wrote ACME challenge to file hult7KCSkUm1SpdaVlh28JhJ9f3J6U6Kv7H-QH3i-0Y
 [notice] Please copy this file to your web server so that it will be served from the URL
 [notice] http://example.com/.well-known/acme-challenge/hult7KCSkUm1SpdaVlh28JhJ9f3J6U6Kv7H-QH3i-0Y
 [notice] After this is complete, run terminus acme-file-verify <site>.<env> example.com
```

You must be a member of the site's team to create challenges.

### 2. Tell Pantheon the challenge is ready to be verified
After you have deployed the ACME challenge, tell Pantheon the challenge is ready to be verified
using the appropriate command below.

#### Using a DNS TXT record:
 * `terminus acme-txt-verify <site>.<env> example.com`
 ```
 [notice] The challenge for example.com is being verified...
 [notice] Ownership verification is complete!
 [notice] Your HTTPS certificate will be deployed to Pantheon's Global CDN shortly.
```

#### Using a file on your existing webserver:
 * `terminus alpha:https:challenge:file:verify <site>.<env> example.com`
 ```
 [notice] The challenge for example.com is being verified...
 [notice] Ownership verification is complete!
 [notice] Your HTTPS certificate will be deployed to Pantheon's Global CDN shortly.
```

For those scripting automation with this plugin, note:
 * The verify commands exit 0 if verification was successful or nonzero if there was an error.
 * When a verification error occurs, it is sometimes necessary to serve a new challenge.  
   Your automation should call the command to obtain the challenge again and see if it has changed.

## Installation
To install this plugin place it in `~/.terminus/plugins/`.

On Mac OS/Linux:
```
mkdir -p ~/.terminus/plugins
curl https://github.com/pantheon-systems/terminus-acme-plugin/archive/2.0.0-alpha1.tar.gz -L | tar -C ~/.terminus/plugins -xvz
```

## Help
Run `terminus help alpha:https:challenge:dns-txt` for help.
