<?php
declare(strict_types=1);
namespace Episciences\MailingList;

use Episciences_Tools;

class MailingList
{
    /** @var int|null */
    protected $_id = null;

    /** @var int|null */
    protected $_rvid = null;

    /** @var string */
    protected $_name = '';

    /** @var string|null */
    protected $_type = null;

    /** @var int */
    protected $_status = 1;

    /** @var array<int> */
    protected $_users = [];

    /** @var array<string> */
    protected $_roles = [];

    /**
     * @param array<string, mixed>|null $options
     */
    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $classMethods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = Episciences_Tools::convertToCamelCase($key, '_', true);
            $method = 'set' . $key;
            if (in_array($method, $classMethods, true)) {
                $this->$method($value);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'rvid' => $this->getRvid(),
            'name' => $this->getName(),
            'type' => $this->getType(),
            'status' => $this->getStatus(),
        ];
    }

    /**
     * Build a standardized mailing list name
     * @param string $rvcode Journal code
     * @param string $subName Sub-name (optional)
     * @return string
     */
    public static function buildFullName(string $rvcode, string $subName = ''): string
    {
        $rvcode = strtolower($rvcode);
        $subName = preg_replace('/[^a-zA-Z0-9._-]/', '', $subName);
        $subName = ltrim($subName, '-');
        $subName = strtolower($subName);
        $suffix = '@' . (defined('DOMAIN') ? DOMAIN : 'episciences.org');

        if ($subName === '') {
            return $rvcode . $suffix;
        }

        return $rvcode . '-' . $subName . $suffix;
    }

    public function getId(): ?int
    {
        return $this->_id;
    }

    public function setId(int $id): self
    {
        $this->_id = $id;
        return $this;
    }

    public function getRvid(): ?int
    {
        return $this->_rvid;
    }

    public function setRvid(int $rvid): self
    {
        $this->_rvid = $rvid;
        return $this;
    }

    public function getName(): string
    {
        return $this->_name;
    }

    public function setName(string $name): self
    {
        $this->_name = $name;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->_type;
    }

    public function setType(?string $type): self
    {
        $this->_type = $type;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->_status;
    }

    public function setStatus(int $status): self
    {
        $this->_status = $status;
        return $this;
    }

    /**
     * @return array<int> List of user IDs
     */
    public function getUsers(): array
    {
        return $this->_users;
    }

    /**
     * @param array<int> $users
     */
    public function setUsers(array $users): self
    {
        $this->_users = $users;
        return $this;
    }

    /**
     * @return array<string> List of role strings
     */
    public function getRoles(): array
    {
        return $this->_roles;
    }

    /**
     * @param array<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->_roles = $roles;
        return $this;
    }
}
