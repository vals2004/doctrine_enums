<?php

declare(strict_types=1);

namespace EnumeumTests\Fixture\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Enumeum\DoctrineEnum\Type\EnumeumType;
use EnumeumTests\Fixture\BaseStatusType;

/**
 * @ORM\Entity
 * @ORM\Table(name="entity")
 */
#[ORM\Entity]
#[ORM\Table(name: "entity")]
class EntityChangedComment
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    /**
     * @ORM\Column(type=enumeum_string, enumType=BaseStatusType::class)
     */
    #[ORM\Column(type: EnumeumType::NAME, enumType: BaseStatusType::class, options: ["comment" => "CHANGED Comment"])]
    private BaseStatusType $status;

    public function __construct(
        int $id,
        BaseStatusType $status,
    ) {
        $this->id = $id;
        $this->status = $status;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getStatus(): BaseStatusType
    {
        return $this->status;
    }
}
