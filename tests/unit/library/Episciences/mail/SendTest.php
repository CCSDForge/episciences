<?php

namespace unit\library\Episciences\mail;

use PHPStan\BetterReflection\Reflection\StringCast\ReflectionEnumCaseStringCast;
use PHPUnit\Framework\TestCase;


use Rector\Symfony\Utils\ValueObject\ReturnTypeChange;
use function PHPUnit\Framework\assertEquals;

require_once dirname(__DIR__, 5) . '/scripts/Script.php';
use Script;

class SendTest extends TestCase
{
    public function testSendMailFromReviewAuthorCopy(){

        \Zend_Registry::set('Zend_Locale', new \Zend_Locale(\Episciences_Review::DEFAULT_LANG));


        $fakePaper = $this->createMock(\Episciences_Paper::class);
        $fakeScript = $this->getMockForAbstractClass(Script::class);

        $fakePaper->setDocid(11131);

        $fakeSend = $this->createMock(\Episciences_Mail_Send::class);

        $sendClass = new \Episciences_Mail_Send();

        $fakeUser = $this->createMock(\Episciences_User::class);

        $fakeUser->setUid(906234);
        $fakeUser->setEmail('fake-mail@episciences.org');
        $fakeUser->setScreenName('fake screenName');

        $fakeTemplatesManager = $this->createMock(\Episciences_Mail_TemplatesManager::class);

        //$fakeTemplate = $this->createMock(\Episciences_Mail_Template::class);

        //$fakeTemplate->setType($fakeTemplatesManager::TYPE_PAPER_SUBMISSION_AUTHOR_COPY);


        try {
            $fakeScript->initTranslator(\Episciences_Review::DEFAULT_LANG);
            $result = $sendClass::sendMailFromReview($fakeUser, $fakeTemplatesManager::TYPE_PAPER_SUBMISSION_AUTHOR_COPY, [], null, null, [] , false, [], [
                'rvCode' => 'test', 'rvId' => 13, 'debug' => true
            ] );

            $this->assertTrue($result);
        } catch (\Exception $e) {
            $this->expectExceptionMessage($e->getMessage());
        }


    }

}
