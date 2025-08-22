<?php

class LanguageConversionResultTest extends PHPUnit\Framework\TestCase
{
    
    public function testSuccessfulResult()
    {
        $original = new Episciences_Language_LanguageCode('en');
        $converted = new Episciences_Language_LanguageCode('eng');
        
        $result = new Episciences_Language_LanguageConversionResult($original, $converted, true);
        
        $this->assertTrue($result->isSuccess());
        $this->assertSame($original, $result->getOriginalCode());
        $this->assertSame($converted, $result->getConvertedCode());
        $this->assertEmpty($result->getErrors());
        $this->assertEmpty($result->getWarnings());
        $this->assertFalse($result->hasErrors());
        $this->assertFalse($result->hasWarnings());
    }
    
    public function testFailedResult()
    {
        $original = new Episciences_Language_LanguageCode('invalid');
        $fallback = new Episciences_Language_LanguageCode('en');
        
        $result = new Episciences_Language_LanguageConversionResult(
            $original, 
            $fallback, 
            false, 
            ['Invalid language code']
        );
        
        $this->assertFalse($result->isSuccess());
        $this->assertSame($original, $result->getOriginalCode());
        $this->assertSame($fallback, $result->getConvertedCode());
        $this->assertEquals(['Invalid language code'], $result->getErrors());
        $this->assertTrue($result->hasErrors());
    }
    
    public function testWithWarnings()
    {
        $original = new Episciences_Language_LanguageCode('en');
        $converted = new Episciences_Language_LanguageCode('eng');
        
        $result = new Episciences_Language_LanguageConversionResult(
            $original, 
            $converted, 
            true, 
            [], 
            ['Conversion may be ambiguous']
        );
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['Conversion may be ambiguous'], $result->getWarnings());
        $this->assertTrue($result->hasWarnings());
        $this->assertFalse($result->hasErrors());
    }
    
    public function testAddError()
    {
        $original = new Episciences_Language_LanguageCode('en');
        $converted = new Episciences_Language_LanguageCode('eng');
        
        $result = new Episciences_Language_LanguageConversionResult($original, $converted, true);
        
        $this->assertTrue($result->isSuccess());
        
        $result->addError('Something went wrong');
        
        $this->assertFalse($result->isSuccess());
        $this->assertEquals(['Something went wrong'], $result->getErrors());
        $this->assertTrue($result->hasErrors());
    }
    
    public function testAddWarning()
    {
        $original = new Episciences_Language_LanguageCode('en');
        $converted = new Episciences_Language_LanguageCode('eng');
        
        $result = new Episciences_Language_LanguageConversionResult($original, $converted, true);
        
        $this->assertFalse($result->hasWarnings());
        
        $result->addWarning('This is a warning');
        
        $this->assertTrue($result->isSuccess()); // Warnings don't affect success
        $this->assertEquals(['This is a warning'], $result->getWarnings());
        $this->assertTrue($result->hasWarnings());
    }
    
    public function testStaticFactoryMethods()
    {
        $original = new Episciences_Language_LanguageCode('en');
        $converted = new Episciences_Language_LanguageCode('eng');
        $fallback = new Episciences_Language_LanguageCode('en');
        
        // Test success factory
        $successResult = Episciences_Language_LanguageConversionResult::success($original, $converted);
        $this->assertTrue($successResult->isSuccess());
        $this->assertSame($original, $successResult->getOriginalCode());
        $this->assertSame($converted, $successResult->getConvertedCode());
        
        // Test failed factory with fallback
        $failedResult = Episciences_Language_LanguageConversionResult::failed(
            $original, 
            'Test error', 
            $fallback
        );
        $this->assertFalse($failedResult->isSuccess());
        $this->assertSame($original, $failedResult->getOriginalCode());
        $this->assertSame($fallback, $failedResult->getConvertedCode());
        $this->assertEquals(['Test error'], $failedResult->getErrors());
        
        // Test failed factory without fallback
        $failedResultNoFallback = Episciences_Language_LanguageConversionResult::failed(
            $original, 
            'Test error'
        );
        $this->assertSame($original, $failedResultNoFallback->getConvertedCode());
    }
}