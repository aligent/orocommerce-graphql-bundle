<?php declare(strict_types=1);

namespace Aligent\GraphQLBundle\EventListener;

use GraphQL\Utils\SchemaPrinter;
use Overblog\GraphQLBundle\Event\SchemaCompiledEvent;
use Overblog\GraphQLBundle\Request\Executor as RequestExecutor;
use Overblog\GraphQLBundle\Request\ParserInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class GraphQLSchemaDumperSubscriber implements EventSubscriberInterface
{
    private RequestExecutor $requestExecutor;

    private string $projectDir;

    private bool $schemaWasRecompiled = false;

    public function __construct(RequestExecutor $requestExecutor, string $projectDir)
    {
        $this->requestExecutor = $requestExecutor;
        $this->projectDir = $projectDir;
    }

    public function onSchemaCompiled(): void
    {
        $this->schemaWasRecompiled = true;
    }

    public function dumpSchema(): void
    {
        if (!$this->schemaWasRecompiled) {
            return;
        }

        file_put_contents(
            "{$this->projectDir}/schema.graphql",
            SchemaPrinter::doPrint($this->requestExecutor->getSchema()),
        ) or die("failed to save {$this->projectDir}/schema.graphql");

        $result = $this->requestExecutor
            ->execute(null, [
                ParserInterface::PARAM_QUERY => <<<GQL
                    query {
                        __schema {
                            types {
                                kind
                                name
                                possibleTypes {
                                    name
                                }
                            }
                        }
                    }
                GQL,
                ParserInterface::PARAM_VARIABLES => [],
            ])
            ->toArray();

        file_put_contents(
            "{$this->projectDir}/schema-fragments.json",
            \json_encode($result, \JSON_PRETTY_PRINT),
        ) or die("failed to save {$this->projectDir}/schema-fragments.json");
    }

    public static function getSubscribedEvents()
    {
        return [
            SchemaCompiledEvent::class => "onSchemaCompiled",
            RequestEvent::class => "dumpSchema",
            ConsoleCommandEvent::class => "dumpSchema",
        ];
    }
}