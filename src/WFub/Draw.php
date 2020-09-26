<?php

namespace WFub;

use Warface\ApiClient;
use Warface\RequestController;
use WFub\Enums\ColorsType;
use WFub\Enums\AchievementType;
use WFub\Enums\UserbarType;
use WFub\Exceptions\DrawExceptions;

class Draw implements DrawInterface
{
    use DrawHelperTrait;

    private array $profile;

    private ApiClient $client;
    private \Imagick $object;

    private object $config, $short;

    /**
     * Draw constructor.
     * @param string $region
     */
    public function __construct(string $region = RequestController::REGION_RU)
    {
        $this->client = new ApiClient($region);

        $this->config = $this->_convertToStd($this->_config);
        $this->short = $this->_convertToStd($this->_multilanguage)->{$this->client->region_lang};
    }

    /**
     * @param string|int $name
     * @param int $server
     */
    public function get(?string $name, int $server): void
    {
        $this->profile = $this->client->user()->stat($name, $server, 1);
        $this->profile['server'] = $server;
    }

    /**
     * @param array $data
     */
    public function edit(array $data): void
    {
        foreach ($data as $key => $value) {
            if (isset($data[$key])) $this->profile[$key] = $data[$key];
        }
    }

    /**
     * @param array $data
     */
    public function add(array $data): void
    {
        $this->profile['list'] = $data;
    }

    /**
     * @param string $ubType
     * @return \Imagick
     * @throws DrawExceptions
     */
    public function create(string $ubType = UserbarType::USER): \Imagick
    {
        $this->object = $this->_readObjectImage($ubType);
        $n_expression = $ubType !== UserbarType::CLAN;

        if (isset($this->profile['list']) && $n_expression) {
            $this->drawAchievement();
        }

        switch ($ubType)
        {
            case UserbarType::USER:
                $this->drawStatistics();
                $this->drawType();
                break;

            case UserbarType::JOIN:
                // TODO: Implementation of the invite userbar.
            case UserbarType::CLAN:
                // TODO: Implementation of the clan userbar.
                break;
        }

        if ($n_expression)
        {
            $this->drawProfile();
            $this->drawRank();
        }

        return $this->object;
    }

    public function drawStatistics(): void
    {
        /**
         * @param string $el
         * @return string
         */
        $g_class = fn (string $el): string => $this->short->classes->{$this->profile['favoritPV' . $el]} ?? $this->short->ub->no_class;

        $data = [
            sprintf('%d %s.', $this->profile['playtime_h'] ?? 0, $this->short->ub->hours),
            $g_class('E'),
            $this->profile['pve_wins'] ?? 0,
            $g_class('P'),
            $this->profile['pvp_all'] ?? 0,
            $this->profile['pvp'] ?? 0
        ];

        $object = $this->_createObjectFont(ColorsType::YELLOW, 5, 'static');
        $static = 12;

        foreach ($data as $value)
            $this->object->annotateImage($object, 317, $static += 7, 0, (string) $value);
    }

    public function drawProfile(): void
    {
        $offset = 0;

        if ($this->profile['clan_name'] !== false)
        {
            $clan = $this->_createObjectFont(ColorsType::YELLOW, 12);
            $this->object->annotateImage($clan, 102, 23, 0, $this->profile['clan_name']);

            $offset = 5;
        }

        $nick = $this->_createObjectFont(ColorsType::WHITE, 14);
        $this->object->annotateImage($nick, 102, 32 + $offset, 0, $this->profile['nickname']);

        $this->object->annotateImage(
            $this->_createObjectFont(ColorsType::WHITE, 12), 102, 45 + $offset, 0,
            sprintf('%s: %s', $this->short->ub->server, $this->short->servers->{$this->profile['server']})
        );
    }

    /**
     * @throws DrawExceptions
     */
    public function drawType(): void
    {
        $image = $this->_readObjectImage('type_' . $this->client->region_lang[0]);

        $this->object->compositeImage($image, \Imagick::COMPOSITE_DEFAULT, 297, 14);
    }

    /**
     * @throws DrawExceptions
     */
    public function drawRank(): void
    {
        $image = $this->_readObjectImage('ranks');
        $image->cropImage(32, 32, 0, ($this->profile['rank_id'] - 1) * 32);

        $this->object->compositeImage($image, \Imagick::COMPOSITE_DEFAULT, 64, 18);
    }

    /**
     * @throws DrawExceptions
     */
    public function drawAchievement(): void
    {
        $getCatalog = $this->client->achievement()->catalog();

        $result = [];
        $mask = [AchievementType::STRIPE, AchievementType::BADGE, AchievementType::MARK];

        foreach ($this->profile['list'] as $key => $value)
        {
            switch ($key)
            {
                case AchievementType::MARK:
                case AchievementType::BADGE:
                case AchievementType::STRIPE:
                    $result[$key] = $this->_parseImage($getCatalog, $key, $value);
                    break;

                default:
                    throw new DrawExceptions('Incorrect type achievement', 2);
            }
        }

        uksort($result, fn ($a, $b) => array_search($a, $mask) > array_search($b, $mask));

        foreach ($result as $type => $value)
        {
            $image = $this->_readObjectImage(basename($value));

            [$column, $x, $y] = $type === AchievementType::STRIPE ? [256, 29, 1] : [64, 0, 0];

            $image->thumbnailImage($column, 64, true);
            $this->object->compositeImage($image, \Imagick::COMPOSITE_DEFAULT, $x, $y);
        }
    }
}