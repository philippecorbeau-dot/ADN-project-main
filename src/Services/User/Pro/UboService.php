<?php

namespace App\Services\User\Pro;

use App\Entity\User\Pro\UboDeclaration;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;

class UboService
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function handleAllUboLogic(User $user, UboDeclaration $uboDeclaration, $submittedShareholders, $rawUbos): UboDeclaration
    {
        // Gérer les UBOs incomplets
        $this->updateIncompleteUboDeclarations($user, $uboDeclaration);

        // Gérer les UBOs refusés
        $uboDeclaration = $this->createNewUboDeclarationIfRefused($user, $uboDeclaration);

        // Suppression des UBOs supprimés
        $this->removeDeletedUbos($uboDeclaration, $rawUbos);

        return $uboDeclaration;
    }

    public function getShareholdersDataArray($shareholders): array
    {
        $dataArray = [];

        foreach ($shareholders as $shareholder) {
            $dataArray[] = [
                'firstName' => $shareholder->getFirstName(),
                'lastName' => $shareholder->getLastName(),
                'addressLine1' => $shareholder->getAddressLine1(),
                'addressLine2' => $shareholder->getAddressLine2(),
                'city' => $shareholder->getCity(),
                'region' => $shareholder->getRegion(),
                'postalCode' => $shareholder->getPostalCode(),
                'country' => $shareholder->getCountry(),
                'nationality' => $shareholder->getNationality(),
                'birthday' => $shareholder->getBirthday() ? $shareholder->getBirthday()->format('Y-m-d') : null,
                'birthplace' => $shareholder->getBirthplace(),
                'birthDepartment' => $shareholder->getBirthDepartment(),
            ];
        }

        return $dataArray;
    }

    public function createNewUboDeclaration(User $user, $submittedShareholders): UboDeclaration
    {
        $pro = $user->getPro();
        $newUboDeclaration = new UboDeclaration();

        $newUboDeclaration->setPro($pro);
        $newUboDeclaration->setStatus(UboDeclaration::STATUS_CREATED);

        foreach ($submittedShareholders as $shareholder) {
            $newUboDeclaration->addUbo($shareholder);
        }

        $pro->addUboDeclaration($newUboDeclaration);

        $this->em->persist($newUboDeclaration);
        $this->em->flush();

        return $newUboDeclaration;
    }

    public function updateIncompleteUboDeclarations(User $user, UboDeclaration $uboDeclaration): void
    {
        if ($uboDeclaration->statusIsIncomplete()) {
            foreach ($uboDeclaration->getUbos() as $ubo) {
                $ubo->setUboDeclaration($uboDeclaration);
                $this->em->persist($ubo);
            }
        }
    }

    public function createNewUboDeclarationIfRefused(User $user, UboDeclaration $uboDeclaration): UboDeclaration
    {
        $pro = $user->getPro();

        if ($uboDeclaration->statusIsRefused()) {
            $newUboDeclaration = new UboDeclaration();

            $newUboDeclaration->setPro($pro);
            $newUboDeclaration->setStatus(UboDeclaration::STATUS_CREATED);

            foreach ($pro->getShareholdersInformations() as $shareholder) {
                $newUboDeclaration->addUbo($shareholder);
            }

            $pro->addUboDeclaration($newUboDeclaration);

            $this->em->persist($newUboDeclaration);
            $this->em->flush();

            return $newUboDeclaration;
        }

        return $uboDeclaration;
    }

    public function removeDeletedUbos(UboDeclaration $uboDeclaration, array $rawUbos): void
    {
        foreach ($rawUbos as $rawUbo) {
            if (isset($rawUbo['remove']) && $rawUbo['remove'] == 'true') {
                foreach ($uboDeclaration->getUbos() as $ubo) {
                    if ($ubo->getId() == $rawUbo['id']) {
                        $uboDeclaration->removeUbo($ubo);
                        $this->em->remove($ubo);
                    }
                }
            }
        }
    }
}
