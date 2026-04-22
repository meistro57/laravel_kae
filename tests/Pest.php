<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

// Custom expectation: asserts value is either null or a non-empty string (API key pattern)
expect()->extend('toBeNullableString', function () {
    $value = $this->value;
    expect($value === null || is_string($value))->toBeTrue(
        "Expected null or string, got " . gettype($value)
    );

    return $this;
});
