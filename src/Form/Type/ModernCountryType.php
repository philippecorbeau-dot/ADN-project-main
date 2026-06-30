<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Intl\Countries;

class ModernCountryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Cette méthode n'est pas utilisée car nous héritons de ChoiceType
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['attr']['class'] = 'modern-country-select';
        $view->vars['attr']['data-modern-select'] = 'true';
        $view->vars['attr']['data-placeholder'] = $options['placeholder'] ?? 'Sélectionner un pays';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $countries = $this->getCountriesWithFlags();
        $choices = [];
        
        foreach ($countries as $country) {
            $choices[$country['flag'] . ' ' . $country['name']] = $country['code'];
        }
        
        $resolver->setDefaults([
            'choices' => $choices,
            'preferred_choices' => ['FR'],
            'placeholder' => 'Sélectionner un pays',
            'required' => true,
            'attr' => [
                'class' => 'modern-country-select',
                'data-modern-select' => 'true',
                'data-placeholder' => 'Sélectionner un pays'
            ]
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }

    private function getCountriesWithFlags(): array
    {
        $countries = [];
        $flags = $this->getCountryFlags();
        
        foreach (Countries::getNames('fr') as $code => $name) {
            $countries[] = [
                'code' => $code,
                'name' => $name,
                'flag' => $flags[$code] ?? '🏳️'
            ];
        }

        // Trier par nom
        usort($countries, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $countries;
    }

    private function getCountryFlags(): array
    {
        return [
            'AF' => '🇦🇫', 'AX' => '🇦🇽', 'AL' => '🇦🇱', 'DZ' => '🇩🇿', 'AS' => '🇦🇸', 'AD' => '🇦🇩', 'AO' => '🇦🇴', 'AI' => '🇦🇮',
            'AQ' => '🇦🇶', 'AG' => '🇦🇬', 'AR' => '🇦🇷', 'AM' => '🇦🇲', 'AW' => '🇦🇼', 'AU' => '🇦🇺', 'AT' => '🇦🇹', 'AZ' => '🇦🇿',
            'BS' => '🇧🇸', 'BH' => '🇧🇭', 'BD' => '🇧🇩', 'BB' => '🇧🇧', 'BY' => '🇧🇾', 'BE' => '🇧🇪', 'BZ' => '🇧🇿', 'BJ' => '🇧🇯',
            'BM' => '🇧🇲', 'BT' => '🇧🇹', 'BO' => '🇧🇴', 'BA' => '🇧🇦', 'BW' => '🇧🇼', 'BV' => '🇧🇻', 'BR' => '🇧🇷', 'IO' => '🇮🇴',
            'BN' => '🇧🇳', 'BG' => '🇧🇬', 'BF' => '🇧🇫', 'BI' => '🇧🇮', 'KH' => '🇰🇭', 'CM' => '🇨🇲', 'CA' => '🇨🇦', 'CV' => '🇨🇻',
            'KY' => '🇰🇾', 'CF' => '🇨🇫', 'TD' => '🇹🇩', 'CL' => '🇨🇱', 'CN' => '🇨🇳', 'CX' => '🇨🇽', 'CC' => '🇨🇨', 'CO' => '🇨🇴',
            'KM' => '🇰🇲', 'CG' => '🇨🇬', 'CD' => '🇨🇩', 'CK' => '🇨🇰', 'CR' => '🇨🇷', 'CI' => '🇨🇮', 'HR' => '🇭🇷', 'CU' => '🇨🇺',
            'CY' => '🇨🇾', 'CZ' => '🇨🇿', 'DK' => '🇩🇰', 'DJ' => '🇩🇯', 'DM' => '🇩🇲', 'DO' => '🇩🇴', 'EC' => '🇪🇨', 'EG' => '🇪🇬',
            'SV' => '🇸🇻', 'GQ' => '🇬🇶', 'ER' => '🇪🇷', 'EE' => '🇪🇪', 'ET' => '🇪🇹', 'FK' => '🇫🇰', 'FO' => '🇫🇴', 'FJ' => '🇫🇯',
            'FI' => '🇫🇮', 'FR' => '🇫🇷', 'GF' => '🇬🇫', 'PF' => '🇵🇫', 'TF' => '🇹🇫', 'GA' => '🇬🇦', 'GM' => '🇬🇲', 'GE' => '🇬🇪',
            'DE' => '🇩🇪', 'GH' => '🇬🇭', 'GI' => '🇬🇮', 'GR' => '🇬🇷', 'GL' => '🇬🇱', 'GD' => '🇬🇩', 'GP' => '🇬🇵', 'GU' => '🇬🇺',
            'GT' => '🇬🇹', 'GG' => '🇬🇬', 'GN' => '🇬🇳', 'GW' => '🇬🇼', 'GY' => '🇬🇾', 'HT' => '🇭🇹', 'HM' => '🇭🇲', 'VA' => '🇻🇦',
            'HN' => '🇭🇳', 'HK' => '🇭🇰', 'HU' => '🇭🇺', 'IS' => '🇮🇸', 'IN' => '🇮🇳', 'ID' => '🇮🇩', 'IR' => '🇮🇷', 'IQ' => '🇮🇶',
            'IE' => '🇮🇪', 'IM' => '🇮🇲', 'IL' => '🇮🇱', 'IT' => '🇮🇹', 'JM' => '🇯🇲', 'JP' => '🇯🇵', 'JE' => '🇯🇪', 'JO' => '🇯🇴',
            'KZ' => '🇰🇿', 'KE' => '🇰🇪', 'KI' => '🇰🇮', 'KP' => '🇰🇵', 'KR' => '🇰🇷', 'KW' => '🇰🇼', 'KG' => '🇰🇬', 'LA' => '🇱🇦',
            'LV' => '🇱🇻', 'LB' => '🇱🇧', 'LS' => '🇱🇸', 'LR' => '🇱🇷', 'LY' => '🇱🇾', 'LI' => '🇱🇮', 'LT' => '🇱🇹', 'LU' => '🇱🇺',
            'MO' => '🇲🇴', 'MK' => '🇲🇰', 'MG' => '🇲🇬', 'MW' => '🇲🇼', 'MY' => '🇲🇾', 'MV' => '🇲🇻', 'ML' => '🇲🇱', 'MT' => '🇲🇹',
            'MH' => '🇲🇭', 'MQ' => '🇲🇶', 'MR' => '🇲🇷', 'MU' => '🇲🇺', 'YT' => '🇾🇹', 'MX' => '🇲🇽', 'FM' => '🇫🇲', 'MD' => '🇲🇩',
            'MC' => '🇲🇨', 'MN' => '🇲🇳', 'ME' => '🇲🇪', 'MS' => '🇲🇸', 'MA' => '🇲🇦', 'MZ' => '🇲🇿', 'MM' => '🇲🇲', 'NA' => '🇳🇦',
            'NR' => '🇳🇷', 'NP' => '🇳🇵', 'NL' => '🇳🇱', 'NC' => '🇳🇨', 'NZ' => '🇳🇿', 'NI' => '🇳🇮', 'NE' => '🇳🇪', 'NG' => '🇳🇬',
            'NU' => '🇳🇺', 'NF' => '🇳🇫', 'MP' => '🇲🇵', 'NO' => '🇳🇴', 'OM' => '🇴🇲', 'PK' => '🇵🇰', 'PW' => '🇵🇼', 'PS' => '🇵🇸',
            'PA' => '🇵🇦', 'PG' => '🇵🇬', 'PY' => '🇵🇾', 'PE' => '🇵🇪', 'PH' => '🇵🇭', 'PN' => '🇵🇳', 'PL' => '🇵🇱', 'PT' => '🇵🇹',
            'PR' => '🇵🇷', 'QA' => '🇶🇦', 'RE' => '🇷🇪', 'RO' => '🇷🇴', 'RU' => '🇷🇺', 'RW' => '🇷🇼', 'BL' => '🇧🇱', 'SH' => '🇸🇭',
            'KN' => '🇰🇳', 'LC' => '🇱🇨', 'MF' => '🇲🇫', 'PM' => '🇵🇲', 'VC' => '🇻🇨', 'WS' => '🇼🇸', 'SM' => '🇸🇲', 'ST' => '🇸🇹',
            'SA' => '🇸🇦', 'SN' => '🇸🇳', 'RS' => '🇷🇸', 'SC' => '🇸🇨', 'SL' => '🇸🇱', 'SG' => '🇸🇬', 'SK' => '🇸🇰', 'SI' => '🇸🇮',
            'SB' => '🇸🇧', 'SO' => '🇸🇴', 'ZA' => '🇿🇦', 'GS' => '🇬🇸', 'ES' => '🇪🇸', 'LK' => '🇱🇰', 'SD' => '🇸🇩', 'SR' => '🇸🇷',
            'SJ' => '🇸🇯', 'SZ' => '🇸🇿', 'SE' => '🇸🇪', 'CH' => '🇨🇭', 'SY' => '🇸🇾', 'TW' => '🇹🇼', 'TJ' => '🇹🇯', 'TZ' => '🇹🇿',
            'TH' => '🇹🇭', 'TL' => '🇹🇱', 'TG' => '🇹🇬', 'TK' => '🇹🇰', 'TO' => '🇹🇴', 'TT' => '🇹🇹', 'TN' => '🇹🇳', 'TR' => '🇹🇷',
            'TM' => '🇹🇲', 'TC' => '🇹🇨', 'TV' => '🇹🇻', 'UG' => '🇺🇬', 'UA' => '🇺🇦', 'AE' => '🇦🇪', 'GB' => '🇬🇧', 'US' => '🇺🇸',
            'UM' => '🇺🇲', 'UY' => '🇺🇾', 'UZ' => '🇺🇿', 'VU' => '🇻🇺', 'VE' => '🇻🇪', 'VN' => '🇻🇳', 'VG' => '🇻🇬', 'VI' => '🇻🇮',
            'WF' => '🇼🇫', 'EH' => '🇪🇭', 'YE' => '🇾🇪', 'ZM' => '🇿🇲', 'ZW' => '🇿🇼'
        ];
    }
} 