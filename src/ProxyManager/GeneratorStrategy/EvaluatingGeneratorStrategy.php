<?php

declare(strict_types=1);

namespace ProxyManager\GeneratorStrategy;

use Laminas\Code\Generator\ClassGenerator;
use Symfony\Component\Filesystem\Filesystem;

use function ini_get;
use function unlink;

/**
 * Generator strategy that produces the code and evaluates it at runtime
 */
class EvaluatingGeneratorStrategy implements GeneratorStrategyInterface
{
    /** @var bool flag indicating whether {@see eval} can be used */
    private $canEval = true;

    /**
     * Constructor
     */
    public function __construct()
    {
        // @codeCoverageIgnoreStart
        $this->canEval = ! ini_get('suhosin.executor.disable_eval');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Evaluates the generated code before returning it
     *
     * {@inheritDoc}
     */
    public function generate(ClassGenerator $classGenerator): string
    {
        $code = $classGenerator->generate();

        // @codeCoverageIgnoreStart
        if (! $this->canEval) {
            $fileName = __DIR__ . '/EvaluatingGeneratorStrategy.php.tmp';
            (new Filesystem())->dumpFile($fileName, "<?php\n" . $code);

            /* @noinspection PhpIncludeInspection */
            require $fileName;
            unlink($fileName);

            return $code;
        }

        // @codeCoverageIgnoreEnd

        /* Handling of issue "Cannot use float as default value for parameter $timestampBegin of type int"
           during eval of `DateTimeZone::getTransitions` where -9223372036854775808 and 9223372036854775807
           are being treated as `float` instead of `int` */
        if (strpos($code ?? '', '$timestampBegin'))
        {
            $code = str_replace(
                'public function getTransitions(int $timestampBegin = -9223372036854775808, int $timestampEnd = 9223372036854775807)',
                'public function getTransitions(int $timestampBegin = PHP_INT_MIN, int $timestampEnd = PHP_INT_MAX)',
                $code);
        }

        eval($code);

        return $code;
    }
}
