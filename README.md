# stagingDuplicatedFix

## Problem

Magento 2 can run into serious issues when content staging is used. If two or more versions of an entity are set to be active at the same time, it will cause a 'duplicate entity' error causing relevant (to the duplicate entry) sections of the site to not function.

## Solution

Running this script will loop through all tables that are used for content staging and clear out any duplicates (entities set to become active at the same time, with the same entity ID). When clearing duplicates, the most recently updated entity will always remain.

## Usage

Copy the `clear-content-staging.php` script into the root of a Magento 2 installation and run from the command line. After running a timestamped SQL file will have been generated that will contain all removed entities. This is in case the entities need to be reimported.

## Versions

Currently, this script has been tested on:
- Magento v2.2.6 Enterprise
