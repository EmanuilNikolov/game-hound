<?php

namespace App\Controller;


use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;
use EN\IgdbApiBundle\Igdb\IgdbWrapperInterface;
use EN\IgdbApiBundle\Igdb\Parameter\ParameterBuilderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class GameController extends AbstractController
{

    /**
     * @var IgdbWrapperInterface
     */
    private $wrapper;

    /**
     * @var ParameterBuilderInterface
     */
    private $builder;

    /**
     * @var DenormalizerInterface
     */
    private $denormalizer;

    /**
     * GameController constructor.
     *
     * @param IgdbWrapperInterface $wrapper
     * @param ParameterBuilderInterface $builder
     * @param DenormalizerInterface $denormalizer
     */
    public function __construct(
      IgdbWrapperInterface $wrapper,
      ParameterBuilderInterface $builder,
      DenormalizerInterface $denormalizer
    ) {
        $this->wrapper = $wrapper;
        $this->builder = $builder;
        $this->denormalizer = $denormalizer;
    }


    /**
     * @Route("/search/{name}", name="game_search", methods={"GET"})
     *
     * @param string $name
     * @param DenormalizerInterface $denormalizer
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function search(
      string $name
    ) {
        $cache = new FilesystemCache();

        if (!$cache->has('games.search.' . $name)) {
            $this->builder
              ->setSearch($name)
              ->setFields('name,slug,cover');
            $games = $this->wrapper->games($this->builder);
            $gamesNormalized = $this->denormalize($games);

            $cache->set('games.search.' . $name, $gamesNormalized);
            dd('kesh');
        }

        $gamesNormalized = $cache->get('games.search.' . $name);

        return $this->render('base.html.twig');
    }

    /**
     * @Route("game/{slug}", name="game_view", methods={"GET"})
     *
     * @param string $slug
     * @param EntityManagerInterface $em
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function view(string $slug, EntityManagerInterface $em)
    {
        $game = $em->getRepository(Game::class)->findOneBy(['slug' => $slug]);

        if (!$game) {
            $this->builder
              ->setSearch($slug)
              ->setFields('name,slug,summary,first_release_date,cover')
              ->setLimit(1);

            /** @var Game $game */
            $game = $this->denormalize($this->wrapper->games($this->builder))[0];

            $em->persist($game);
            $em->flush();
        }

        return $this->render('base.html.twig');
    }

    /**
     * @Route("test")
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function test()
    {
        $this->builder->setLimit(1)->setSearch('mass effect andromeda');
        $game = $this->wrapper->games($this->builder);
        dd($game);
        $cache = new FilesystemCache();
        dd($cache->get('games.search'));
    }

    /**
     * @param array $games
     *
     * @return array
     */
    private function denormalize(array $games): array
    {
        $gamesNormalized = [];

        foreach ($games as $game) {
            $gamesNormalized[] = $this->denormalizer->denormalize($game, Game::class);
        }

        return $gamesNormalized;
    }
}