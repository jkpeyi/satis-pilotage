<?php

use App\Actions\Referential\ArchiveVersion;
use App\Actions\Referential\SaveVersion;
use App\Actions\Referential\SetReferenceVersion;
use App\Enums\VerificationStatus;
use App\Enums\VersionStatus;
use App\Models\Product;
use App\Models\Version;

it('RG-08: accepts a baseline version without customer as reference', function () {
    $product = Product::factory()->create();
    $baseline = Version::factory()->baseline()->for($product)->create();

    app(SetReferenceVersion::class)->handle($product, $baseline);

    expect($product->refresh()->referenceVersion->is($baseline))->toBeTrue()
        ->and($baseline->isBaseline())->toBeTrue()
        ->and($baseline->refresh()->isReferenceOfItsProduct())->toBeTrue();
});

it('RG-08: refuses a reference version from another product', function () {
    $product = Product::factory()->create();
    $otherVersion = Version::factory()->create();

    app(SetReferenceVersion::class)->handle($product, $otherVersion);
})->throws(DomainException::class);

it('RG-08: refuses an archived version as reference', function () {
    $version = Version::factory()->create();
    $version->archive();

    app(SetReferenceVersion::class)->handle($version->product, $version);
})->throws(DomainException::class);

it('RG-08: removes the reference when the reference version is archived', function () {
    $version = Version::factory()->create();
    app(SetReferenceVersion::class)->handle($version->product, $version);

    app(ArchiveVersion::class)->handle($version->refresh());

    expect($version->product->refresh()->reference_version_id)->toBeNull()
        ->and($version->refresh()->isArchived())->toBeTrue();
});

it('RG-08: removes the reference when the reference version moves to another product', function () {
    $version = Version::factory()->create();
    $formerProduct = $version->product;
    app(SetReferenceVersion::class)->handle($formerProduct, $version);
    $newProduct = Product::factory()->create();

    app(SaveVersion::class)->handle([
        'product_id' => $newProduct->id,
        'customer_id' => $version->customer_id,
        'name' => $version->name,
        'status' => VersionStatus::InProduction,
        'is_historical_pivot' => false,
        'technical_owner_id' => null,
        'verification_status' => VerificationStatus::ToVerify,
        'notes' => null,
    ], $version->refresh());

    expect($formerProduct->refresh()->reference_version_id)->toBeNull()
        ->and($version->refresh()->product_id)->toBe($newProduct->id);
});
