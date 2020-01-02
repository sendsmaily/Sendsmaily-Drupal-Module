# Smaily for Drupal

Smaily email marketing and automation module for Drupal.
Includes a simple sign-up form to add new subscribers to Smaily subscribers list.

## Features

### Drupal Newsletter Subscription
- Add new subscribers to Smaily subscribers list.
- Select autoresponder to send automated emails.

## Requirements
- Drupal 8

## Documentation & Support
Online documentation with help is available at the [Knowledgebase](http://help.smaily.com/en/support/home).

## Contribution
All development for Smaily for Drupal is handled via [GitHub](https://github.com/sendsmaily/smaily-drupal-module).

Opening new issues and submitting pull requests are welcome.

## Installation

- Place smaily_for_drupal into core/modules/ folder.
- Install module under Extend.
- Validate and save your Smaily API credentials.
- Go to Structure -> Block layout.
- Place "Smaily Newsletter" block where you wish.

## Success/Failure pages
- Success and Failure pages can be set in Block Settings.
- One success and failure page is created by Smaily for use.
- More pages can be created using the Smaily Response Pages content type.
- "Redirect back" redirects to last page user was on, handles responses with feedback.
- Smaily API provides a response via URL arguments, these can be accessed with \Drupal::request()->query->all()['message']

## Troubleshooting
- No response when placing Smaily Newsletter block
Install PHP cURL library and restart Apache.