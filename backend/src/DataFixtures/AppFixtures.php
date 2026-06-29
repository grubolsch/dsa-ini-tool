<?php

namespace App\DataFixtures;

use App\Entity\Encounter;
use App\Entity\EncounterMonster;
use App\Entity\Hero;
use App\Entity\MonsterTemplate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Party of heroes (no health).
        $heroData = [
            ['Aria the Brave', 14],
            ['Thorgar Ironfist', 11],
            ['Lyra Moonwhisper', 16],
            ['Brother Aldric', 9],
        ];
        foreach ($heroData as [$name, $ini]) {
            $hero = new Hero();
            $hero->setName($name);
            $hero->setInitiative($ini);
            $manager->persist($hero);
        }

        // Reusable monster templates.
        $goblin = new MonsterTemplate();
        $goblin->setName('Goblin');
        $goblin->setInitiative(8);
        $goblin->setLe(15);
        $goblin->setDescription('A small, vicious humanoid. Attacks in packs.');
        $manager->persist($goblin);

        $ogre = new MonsterTemplate();
        $ogre->setName('Ogre');
        $ogre->setInitiative(5);
        $ogre->setLe(45);
        $ogre->setDescription('A hulking brute with a massive club. Slow but devastating.');
        $manager->persist($ogre);

        $wolf = new MonsterTemplate();
        $wolf->setName('Dire Wolf');
        $wolf->setInitiative(12);
        $wolf->setLe(22);
        $wolf->setDescription('A large, fast predator. Hunts in a pack.');
        $manager->persist($wolf);

        // Sample encounter (newest) with monsters; same template added twice.
        $encounter = new Encounter();
        $encounter->setName('Goblin Ambush');
        $manager->persist($encounter);

        foreach ([$goblin, $goblin, $goblin, $ogre] as $template) {
            $em = new EncounterMonster();
            $em->setEncounter($encounter);
            $em->setMonsterTemplate($template);
            $em->setName($template->getName());
            $em->setPicture($template->getPicture());
            $em->setInitiative($template->getInitiative());
            $em->setLe($template->getLe());
            $em->setDescription($template->getDescription());
            $encounter->addMonster($em);
            $manager->persist($em);
        }

        $manager->flush();
    }
}
