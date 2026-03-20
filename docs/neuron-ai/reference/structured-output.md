# Structured Output

Enforce AI agents to output data in a structured format (JSON) based on a PHP class schema.

## How to use Structured Output

Define a class with strictly typed properties and use `structured()` on the agent:

```php
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\Attributes\SchemaProperty;
use Symfony\Component\Validator\Constraints as Assert;

class Person
{
    #[SchemaProperty(description: 'The first name of the person')]
    #[Assert\NotBlank]
    public string $firstName;

    #[SchemaProperty(description: 'The last name of the person')]
    #[Assert\NotBlank]
    public string $lastName;

    #[SchemaProperty(description: 'The age of the person')]
    #[Assert\Type('integer')]
    public int $age;
}

$person = MyAgent::make()
    ->structured(Person::class)
    ->chat(new UserMessage("Extract information from: John Doe is 30 years old."))
    ->getMessage()
    ->getContent();

// $person is an instance of Person class
```

## SchemaProperty

The `SchemaProperty` attribute controls the JSON schema parameters passed to the LLM.

## Validation Rules

Neuron uses Symfony Validator constraints to verify LLM output. If validation fails, it automatically retries.

## Max Retries

Customize the number of retries (default is 1):

```php
$agent->maxRetries(3);
```

## Nested Classes and Arrays

You can use other objects as property types or arrays of objects using `anyOf`:

```php
class Tag { public string $name; }

class Article
{
    #[SchemaProperty(anyOf: [Tag::class])]
    public array $tags;
}
```
