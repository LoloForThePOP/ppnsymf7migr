<?php

namespace App\Tests\Unit;

use App\Entity\PPBase;
use App\Service\LiveSavePP;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LiveSavePPTest extends TestCase
{
    public function testSanitizesTextDescriptionBeforeSave(): void
    {
        $presentation = new PPBase();

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn($presentation);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->expects(self::once())->method('flush');

        $security = $this->createMock(Security::class);
        $validator = $this->createMock(ValidatorInterface::class);

        $sanitizer = new class() implements HtmlSanitizerInterface {
            public ?string $lastInput = null;

            public function sanitize(string $input): string
            {
                $this->lastInput = $input;
                return '[sanitized]';
            }

            public function sanitizeFor(string $element, string $input): string
            {
                return $this->sanitize($input);
            }
        };

        $service = new LiveSavePP($security, $em, $validator, $sanitizer);

        $rawContent = '<p>Hello</p><script>alert(1)</script>';
        $service->hydrate('ppbase', 1, 'textDescription', null, null, $rawContent);
        $service->save();

        self::assertSame($rawContent, $sanitizer->lastInput);
        self::assertSame('[sanitized]', $service->getContent());
        self::assertSame('[sanitized]', $presentation->getTextDescription());
    }
}
