<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BREField;
use App\Models\BREDataType;

class BREFieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $textInputType = BREDataType::where('name', 'text-input')->first()->id;
        $dropdownType = BREDataType::where('name', 'dropdown')->first()->id;
        $checkboxType = BREDataType::where('name', 'checkbox')->first()->id;
        $toggleType = BREDataType::where('name', 'toggle')->first()->id;
        $dateType = BREDataType::where('name', 'date')->first()->id;
        $textAreaType = BREDataType::where('name', 'text-area')->first()->id;
        $buttonType = BREDataType::where('name', 'button')->first()->id;
        $radioType = BREDataType::where('name', 'radio')->first()->id;
        $booleanType = BREDataType::where('name', 'true-false')->first()->id;
        $numberInputType = BREDataType::where('name', 'number-input')->first()->id;
        $textInfoType = BREDataType::where('name', 'text-info')->first()->id;
        $linkType = BREDataType::where('name', 'link')->first()->id;

        $breFields = [
            ['name' => 'baseAmount', 'label' => 'Base Amount', 'data_type_id' => $numberInputType],
            ['name' => 'benefitMonth', 'label' => 'Benefit Month', 'data_type_id' => $dateType],
            ['name' => 'benefitPlanEndDate', 'label' => 'Benefit Plan End Date', 'data_type_id' => $dateType],
            ['name' => 'benefitPlanStartDate', 'label' => 'Benefit Plan Start Date', 'data_type_id' => $dateType],
            ['name' => 'caseID', 'label' => 'Case ID', 'data_type_id' => $textInputType],
            ['name' => 'childrenAmount', 'label' => 'Children Amount', 'data_type_id' => $numberInputType],
            ['name' => 'confirmedPregnancyDate', 'label' => 'Confirmed Pregnancy Date', 'data_type_id' => $dateType],
            ['name' => 'dependentsList', 'label' => 'Dependents List', 'data_type_id' => $textInputType],
            ['name' => 'dietSupplementAmount', 'label' => 'Diet Supplement Amount', 'data_type_id' => $numberInputType],
            ['name' => 'effectiveDOB', 'label' => 'Effective DOB', 'data_type_id' => $dateType],
            ['name' => 'eligibleForBlenderSupplement', 'label' => 'Eligible for Blender Supplement', 'data_type_id' => $booleanType],
            ['name' => 'eligibleForHighProteinSupplement', 'label' => 'Eligible for High Protein Supplement', 'data_type_id' => $booleanType],
            ['name' => 'errorMsg', 'label' => 'Error Message', 'data_type_id' => $textInputType],
            ['name' => 'estimatedDeliveryDate', 'label' => 'Estimated Delivery Date', 'data_type_id' => $dateType],
            ['name' => 'expectedDueDate', 'label' => 'Expected Due Date', 'data_type_id' => $dateType],
            ['name' => 'familyComposition', 'label' => 'Family Composition', 'data_type_id' => $textInputType],
            ['name' => 'familyUnitDeemedIneligible', 'label' => 'Family Unit Deemed Ineligible', 'data_type_id' => $booleanType],
            ['name' => 'familyUnitInPay', 'label' => 'Family Unit In Pay', 'data_type_id' => $booleanType],
            ['name' => 'familyUnitInPayForDecember', 'label' => 'Family Unit In Pay For December', 'data_type_id' => $booleanType],
            ['name' => 'familyUnitInPayForMonth', 'label' => 'Family Unit In Pay For Month', 'data_type_id' => $booleanType],
            ['name' => 'familyUnitSize', 'label' => 'Family Unit Size', 'data_type_id' => $numberInputType],
            ['name' => 'guideDogDocumentationType', 'label' => 'Guide Dog Documentation Type', 'data_type_id' => $textInputType],
            ['name' => 'hasPWDDesignation', 'label' => 'Has PWD Designation', 'data_type_id' => $booleanType],
            ['name' => 'highProteinConditions', 'label' => 'High Protein Conditions', 'data_type_id' => $textInputType],
            ['name' => 'inPayOfAnyOtherSupplement', 'label' => 'In Pay Of Any Other Supplement', 'data_type_id' => $booleanType],
            ['name' => 'inPayOfDA', 'label' => 'In Pay Of DA', 'data_type_id' => $booleanType],
            ['name' => 'isActiveOnCase', 'label' => 'Is Active On Case', 'data_type_id' => $booleanType],
            ['name' => 'isEligible', 'label' => 'Is Eligible', 'data_type_id' => $booleanType],
            ['name' => 'isEligibleForPayment', 'label' => 'Is Eligible For Payment', 'data_type_id' => $booleanType],
            ['name' => 'isPregnant', 'label' => 'Is Pregnant', 'data_type_id' => $booleanType],
            ['name' => 'mnsEligibilityAssessmentDate', 'label' => 'MNS Eligibility Assessment Date', 'data_type_id' => $dateType],
            ['name' => 'mnsPaymentAlreadyMade', 'label' => 'MNS Payment Already Made', 'data_type_id' => $booleanType],
            ['name' => 'mnsReviewDate', 'label' => 'MNS Review Date', 'data_type_id' => $dateType],
            ['name' => 'mnsType', 'label' => 'MNS Type', 'data_type_id' => $textInputType],
            ['name' => 'numberOfChildren', 'label' => 'Number of Children', 'data_type_id' => $numberInputType],
            ['name' => 'numberOfDogTeams', 'label' => 'Number of Dog Teams', 'data_type_id' => $numberInputType],
            ['name' => 'numberOfRecipients', 'label' => 'Number of Recipients', 'data_type_id' => $numberInputType],
            ['name' => 'nutritionalSupplementDietPayment', 'label' => 'Nutritional Supplement Diet Payment', 'data_type_id' => $numberInputType],
            ['name' => 'nutritionalSupplementVitaminsPayment', 'label' => 'Nutritional Supplement Vitamins Payment', 'data_type_id' => $numberInputType],
            ['name' => 'personID', 'label' => 'Person ID', 'data_type_id' => $textInputType],
            ['name' => 'person1age', 'label' => 'Person 1 Age', 'data_type_id' => $numberInputType],
            ['name' => 'person2age', 'label' => 'Person 2 Age', 'data_type_id' => $numberInputType],
            ['name' => 'person1hasPPMBStatus', 'label' => 'Person 1 Has PPMB Status', 'data_type_id' => $booleanType],
            ['name' => 'person2hasPPMBStatus', 'label' => 'Person 2 Has PPMB Status', 'data_type_id' => $booleanType],
            ['name' => 'person1HasPWDStatus', 'label' => 'Person 1 Has PWD Status', 'data_type_id' => $booleanType],
            ['name' => 'person2HasPWDStatus', 'label' => 'Person 2 Has PWD Status', 'data_type_id' => $booleanType],
            ['name' => 'reason', 'label' => 'Reason', 'data_type_id' => $textInputType],
            ['name' => 'reasonsForIneligibility', 'label' => 'Reasons For Ineligibility', 'data_type_id' => $textInputType],
            ['name' => 'receivingDietSupplement', 'label' => 'Receiving Diet Supplement', 'data_type_id' => $booleanType],
            ['name' => 'receivingInKind', 'label' => 'Receiving In Kind', 'data_type_id' => $booleanType],
            ['name' => 'receivingScheduleCAppealAwardGP', 'label' => 'Receiving Schedule C Appeal Award GP', 'data_type_id' => $booleanType],
            ['name' => 'residingInCareFacility', 'label' => 'Residing In Care Facility', 'data_type_id' => $booleanType],
            ['name' => 'residingInCareFacilityOtherThanAlcoholOrDrug', 'label' => 'Residing In Care Facility Other Than Alcohol Or Drug', 'data_type_id' => $booleanType],
            ['name' => 'shelterCostConfirmed', 'label' => 'Shelter Cost Confirmed', 'data_type_id' => $booleanType],
            ['name' => 'shelterCosts', 'label' => 'Shelter Costs', 'data_type_id' => $numberInputType],
            ['name' => 'singleNoDependentChildren', 'label' => 'Single No Dependent Children', 'data_type_id' => $booleanType],
            ['name' => 'singleOrMultiplePregnancy', 'label' => 'Single Or Multiple Pregnancy', 'data_type_id' => $textInputType],
            ['name' => 'supplementAmount', 'label' => 'Supplement Amount', 'data_type_id' => $numberInputType],
            ['name' => 'usesGuideDog', 'label' => 'Uses Guide Dog', 'data_type_id' => $booleanType],
            ['name' => 'verificationOfNeedConfirmed', 'label' => 'Verification Of Need Confirmed', 'data_type_id' => $booleanType],
            ['name' => 'writtenConfirmationOfPregnancy', 'label' => 'Written Confirmation Of Pregnancy', 'data_type_id' => $booleanType],
            ['name' => 'writtenConfirmationProvided', 'label' => 'Written Confirmation Provided', 'data_type_id' => $booleanType],
        ];

        foreach ($breFields as $breField) {
            BREField::create($breField);
        }
    }
}
