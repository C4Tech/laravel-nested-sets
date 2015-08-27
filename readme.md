# Laravel Nested Sets

A package for bootstrapping nested set implementations. Made with love
by C4 Tech and Design.

[![Latest Stable Version](https://poser.pugx.org/c4tech/nested-set/v/stable)](https://packagist.org/packages/c4tech/nested-set)
[![Build Status](https://travis-ci.org/C4Tech/laravel-nested-sets.svg?branch=master)](https://travis-ci.org/C4Tech/laravel-nested-sets)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/C4Tech/laravel-nested-sets/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/C4Tech/laravel-nested-sets/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/C4Tech/laravel-nested-sets/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/C4Tech/laravel-nested-sets/?branch=master)

### Repository

Our nested set repository provides a cacheable interface to the
underlying model. This builds on top of the base Repository class from
c4tech/support.

The property refering to the underlying model (static `$model`) is expected
to be a reference to a config item but can be a hardcoded class name.

### Model

Our nested set model bridges the baum/baum implementation with our own
base Model class from c4tech/support.


## Installation and setup

1. Add `"c4tech/nested-set": "2.x"` to your composer requirements and run `composer update`.
2. Create your own classes to extend `Model` and `Repository`.
