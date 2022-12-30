<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Exception;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AppendixRepository")
 */
class Appendix extends AbstractEudoEntity
{
	private $match = [
		'AC_PRIME_PJ_FACTURE' => ["facture", "Facture assurance PJ"],
		'AC_PRIME_RCP_FACTURE' => ["facture", "Facture assurance RCP"],
		'AC_PRIME_RCP_PJ_APPEL' => ["prime-d-assurance", "Appel de prime RCP et PJ"],
		'AC_PRIME_RCP_PJ_MED' => ["prime-d-assurance", "Dernier appel de prime RCP et PJ"],
		'AC_PRIME_RCP_PJ_RAPPEL' => ["prime-d-assurance", "Rappel de prime RCP et PJ"],
		'AC_SOUS_PJ_ATTESTATION' => ["attestation", "Attestation PJ"],
		'AC_SOUS_PJ_DEVIS' => ["devis", "Devis d'adhésion PJ"],
		'AC_SOUS_RCP_ATTESTATION' => ["attestation", "Attestation RCP"],
		'AC_SOUS_RCP_DEVIS' => ["devis", "Devis adhésion RCP"],
		'AC_SOUS_RCP_PJ_DEVIS' => ["devis", "Devis adhésion RCP et PJ"],
		'AGENCE_PRIME_GLI_FACTURE' => ["facture", "Facture GLI"],
		'AGENCE_PRIME_MRP_APPEL' => ["prime-d-assurance", "Appel de prime MRP"],
		'AGENCE_PRIME_MRP_FACTURE' => ["facture", "Facture MRP"],
		'AGENCE_PRIME_MRP_MED' => ["prime-d-assurance", "Dernier appel de prime MRP"],
		'AGENCE_PRIME_MRP_RAPPEL' => ["prime-d-assurance", "Rappel de prime MRP"],
		'AGENCE_PRIME_PJ_APPEL' => ["prime-d-assurance", "Appel de prime PJ"],
		'AGENCE_PRIME_MRP_PJ_APPEL' => ["prime-d-assurance", "Appel de prime MRP PJ"],
		'AGENCE_PRIME_PJ_FACTURE' => ["facture", "Facture PJ"],
		'AGENCE_PRIME_PJ_RAPPEL' => ["prime-d-assurance", "Rappel de prime PJ"],
		'AGENCE_PRIME_PJ_MED' => ["prime-d-assurance", "Dernier appel de prime PJ"],
		'AGENCE_PRIME_PNO_FACTURE' => ["facture", "Facture PNO"],
		'AGENCE_SOUS_MRP_ATTESTATION' => ["attestation", "Attestation MRP"],
		'AGENCE_SOUS_PJ_ATTESTATION' => ["attestation", "Attestation PJ Agence"],
		'AGENCE_SOUS_PJ_VP_ATTESTATION' => ["attestation", "Attestation PJ option Vie Privée"],
		'AGENCE_SOUS_PJ_VPT_ATTESTATION' => ["attestation", "Attestation PJ option Vie Privée thiers"],
		'AC_SOUS_SNPI_DEVIS' => ["devis", "Devis d'adhésion CACI"],
		'AC_SOUS_SNPI_BULLETIN' => ["devis", "Bulletin d'adhésion CACI"],
		'AGENCE_COT_SNPI_APPEL' => ["appel-de-cotisation", "Appel de cotisation SNPI"],
		'AGENCE_COT_SNPI_AVOIR' => ["facture", "Avoir cotisation SNPI"],
		'AGENCE_COT_SNPI_FACTURE' => ["facture", "Facture cotisation SNPI"],
		'AGENCE_COT_SNPI_RELANCE' => ["appel-de-cotisation", "Rappel de cotisation SNPI"],
		'AGENCE_COT_SNPI_REV_TRV_APPEL' => ["appel-de-cotisation", "Appel de Cotisation REV / TRV"],
		'AGENCE_COT_SNPI_REV_TRV_MED' => ["appel-de-cotisation", "Mise en Demeure REV / TRV"],
		'AG_RESIL_GFA_MISE_DEMEURE_PIECES_COMPTA' => ["garantie-financiere", "Mise en Demeure Bilan et attest GFA"],
		'AGENCE_AUDIT_GFA_DEMANDE_PIECES_COMPTA' => ["garantie-financiere", "Demande pièces comptables"],
		'AGENCE_AUDIT_GFA_INFO_ADHERENT' => ["garantie-financiere", "Audit information adhérent"],
		'AGENCE_AUDIT_GFA_RELANCE_PIECES_COMPTA' => ["garantie-financiere", "Rappel demande pièce comptable"],
		'AGENCE_GFA_DEMANDE_PIECES_COMPTA' => ["garantie-financiere", "Demande pièces comptables"],
		'AGENCE_GFA_DEMANDE_PIECES_COMPTA_RELANCE' => ["garantie-financiere", "Rappel demande pièces comptables"],
		'AGENCE_PACK_SIGN_VHS' => ["facture", "Facture Pack de signatures électroniques"],
		'AGENCE_PRIME_VHS_APPEL' => ["appel-de-prime", "Appel de prime VHS Assurances"],
		'AGENCE_PRIME_VHS_AVOIR_GF' => ["facture", "Avoir sur prime Garantie Financière"],
		'AGENCE_PRIME_VHS_AVOIR_RCP' => ["facture", "Avoir sur prime RCP"],
		'AGENCE_PRIME_VHS_FACTURE_GF' => ["facture", "Facture Garantie(s) Financière(s)"],
		'AGENCE_PRIME_VHS_FACTURE_RCP' => ["facture", "Facture RCP"],
		'AGENCE_PRIME_VHS_MISE_EN_DEMEURE' => ["appel-de-prime", "Mise en Demeure prime VHS Assurances"],
		'AGENCE_PRIME_VHS_RELANCE' => ["appel-de-prime", "Rappel de prime VHS Assurances"],
		'AGENCE_SOUS_GF_ATTESTATION_GESTION' => ["attestation", "Attestation Garantie Financière - Gestion"],
		'AGENCE_SOUS_GFA_AFFICHETTE_GESTION' => ["garantie-financiere", "Affichette Gestion"],
		'AGENCE_SOUS_GFA_AFFICHETTE_SYNDIC' => ["garantie-financiere", "Affichette Syndic"],
		'AGENCE_SOUS_GFA_AFFICHETTE_TRANSACTION' => ["garantie-financiere", "Affichette Transaction avec perception"],
		'AGENCE_SOUS_GFA88_AFFICHETTE' => ["garantie-financiere", "Affichette Transaction avec maniement"],
		'AGENCE_SOUS_GFA88_ATTESTATION' => ["attestation", "Attestation Garantie Financière Transaction avec maniement"],
		'AGENCE_SOUS_GFAG_ATTESTATION' => ["attestation", "Attestation Garantie Financière - Gestion"],
		'AGENCE_SOUS_GFAS_ATTESTATION' => ["attestation", "Attestation Garantie Financière - Syndic"],
		'AGENCE_SOUS_GFAT_ATTESTATION' => ["attestation", "Attestation Garantie Financière - Transaction avec Perception"],
		'AGENCE_SOUS_GFS80_AFFICHETTE' => ["garantie-financiere", "Affichette Transaction sans perception"],
		'AGENCE_SOUS_GFS80_ATTESTATION' => ["attestation", "Attestation Garantie Financière - Transaction sans Perception"],
		'AGENCE_SOUS_RCP_AA_ATTESTATION_CONSEIL_INVEST' => ["attestation", "Attestation RCP - Conseil en investissement"],
		'AGENCE_SOUS_RCP_AA_ATTESTATION_INTER_ASSUR' => ["attestation", "Attestation RCP - Inter assurance"],
		'AGENCE_SOUS_RCP_AA_ATTESTATION_IOB' => ["attestation", "Attestation RCP - IOB"],
		'AGENCE_SOUS_RCP_ATTESTATION' => ["attestation", "Attestation RCP Agence"],
		'ATTESTATION_GFA88' => ["attestation", "Attestation Garantie Financière - Transaction avec maniement"],
		'ATTESTATION_GFAG' => ["attestation", "Attestation Garantie Financière - Gestion"],
		'ATTESTATION_GFAS' => ["attestation", "Attestation Garantie Financière - Syndic"],
		'ATTESTATION_GFAT' => ["attestation", "Attestation Garantie Financière - Transaction avec perception"],
		'ATTESTATION_GFS80' => ["attestation", "Attestation Garantie Financière - Transaction sans maniement"],
		'ATTESTATION_RCP' => ["attestation", "Attestation RCP"],
		'ATTESTATION_RCP_AA' => ["attestation", "Attestation RCP Act Accessoire sans orias"],
		'ATTESTATION_RCP_ACT_ACC_SANS_ORIAS' => ["attestation", "Attestation RCP Act Accessoire sans orias"],
		'ATTESTATION_RCP_EXPERT' => ["attestation", "Attestation RCP Expert"],
		'COURRIER_ART55' => ["garantie-financiere", "Demande attestation Art55"],
		'COURRIER_ART55_MED_AR' => ["garantie-financiere", "Mise en Demeure Attestation art55"],
		'AC_FORMATION_FOAD_CONVENTION' => ["convention", "Convention de formation"],
		'AC_FORMATION_PRESENTIEL_CONVENTION' => ["convention", "Convention de formation"],
		'AC_FORMATION_PRESENTIEL_FOAD_ATTESTATION' => ["attestation", "Attestation de formation"],
		'AGENCE_FORMATION_FOAD_CONVENTION' => ["convention", "Convention de formation"],
		'AGENCE_FORMATION_FOAD_FACTURE' => ["facture", "Facture de formation"],
		'AGENCE_FORMATION_PRESENTIEL_ATTESTATION_SALARIE' => ["attestation", "Attestation de formation"],
		'AGENCE_FORMATION_PRESENTIEL_CONVENTION_SALARIE' => ["convention", "Convention de formation"],
		'AGENCE_FORMATION_PRESENTIEL_FACTURE' => ["facture", "Facture de formation"],
		'AGENCE_FORMATION_PRESENTIEL_FOAD_ATTESTATION_GERANT' => ["attestation", "Attestation de formation"],
		'ATTESTATION_FORMATION' => ["attestation", "Attestation de formation"],
		'CONVENTION_FORMATION' => ["convention", "Convention de formation"],
		'FORM_FACTURE' => ["facture", "Facture de formation"],
		'AC_SOUS_RCP_BULLETIN' => ["bulletin", "Bulletin d'adhésion contrat RCP"],
		'AC_SOUS_PJ_BULLETIN' => ["bulletin", "Bulletin d'adhésion contrat PJ"],
		'AVOIR_FORMATION' => ["facture", "Avoir formation"]
	];

	/**
	 * @ORM\Column(type="string", length=255)
	 */
	private $link;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Contact")
	 * @ORM\JoinColumn(nullable=true)
	 */
	private $contact;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Company")
	 * @ORM\JoinColumn(nullable=true)
	 */
	private $company;

	/**
	 * @ORM\Column(type="string", length=50)
	 */
	private $type;

	/**
	 * @ORM\Column(type="string", length=150)
	 */
	private $filename;

	/**
	 * @ORM\Column(type="string", length=4, nullable=true)
	 */
	private $ext;

	/**
	 * @ORM\Column(type="integer")
	 */
	private $size;

	/**
	 * @ORM\Column(type="string", length=150, nullable=true)
	 */
	private $entityType;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
	private $entityId;

	/**
	 * @ORM\Column(type="string", length=255)
	 */
	private $title;

	/**
	 * @ORM\Column(type="boolean", nullable=true)
	 */
	private $public;

	public function getLink(): ?string
	{
		return $this->link;
	}

	public function setLink(string $link): self
	{
		$this->link = $link;

		return $this;
	}

	public function getContact(): ?Contact
	{
		return $this->contact;
	}

	public function setContact(?Contact $contact): self
	{
		$this->contact = $contact;

		return $this;
	}

	public function setContactId(?int $contact_id): self
	{
		if( $contact_id ){

			$contact = new Contact($contact_id);
			$this->setContact($contact);
		}

		return $this;
	}

	public function getCompany(): ?Company
	{
		return $this->company;
	}

	public function setCompany(?Company $company): self
	{
		$this->company = $company;

		return $this;
	}

	public function setCompanyId(?int $company_id): self
	{
		if( $company_id ){

			$company = new Company();
			$company->setId($company_id);

			$this->setCompany($company);
		}

		return $this;
	}

	public function getType(): ?string
	{
		return $this->type;
	}

	public function setType(string $type): self
	{
		$this->type = $type;

		return $this;
	}

	public function getFilename($clean=false): ?string
	{
		if( $clean )
			return strtolower(preg_replace('/_PM[0-9]*_PP[0-9]*_[0-9]{8}/', '_', $this->filename));

		return $this->filename;
	}

	public function setFilename(string $filename): self
	{
		$this->filename = $filename;

		return $this;
	}

	public function getSize(): ?int
	{
		return $this->size;
	}

	public function setSize(int $size): self
	{
		$this->size = $size;

		return $this;
	}

	public function getEntityId(): ?int
	{
		return $this->entityId;
	}

	public function setEntityId(?int $entityId): self
	{
		$this->entityId = $entityId;

		return $this;
	}

	public function getEntityType(): ?string
	{
		return $this->entityType;
	}

	public function setEntityType(?string $entityType): self
	{
		$this->entityType = $entityType;

		return $this;
	}

	public function getExt(): ?string
	{
		return $this->ext;
	}

	public function setExt(?string $ext): self
	{
		switch ($ext){

			case 'Fichier PDF':
				$ext = 'pdf';
				break;

			default:
				$ext=null;
		}

		$this->ext = $ext;

		return $this;
	}

	public function getTitle(): ?string
	{
		return $this->title;
	}

	public function setTitle(string $title): self
	{
		if( isset($this->match[$title]) ){

			$match = $this->match[$title];

			$this->type = $match[0];
			$this->title = $match[1];
			$this->public = true;
		}
		else{

			$this->type = 'divers';
			$this->title = ucfirst(str_replace('_', ' ', mb_strtolower($title, 'UTF-8')));
			$this->public = false;
		}

		return $this;
	}

	public function getPublic(): ?bool
	{
		return $this->public;
	}

	public function setPublic($public): self
	{
		$this->public = $this->formatBool($public);

		return $this;
	}
}
