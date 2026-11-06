<?php

/*
| Choice lists of the RIC National Database form.
| The registration form and its validation both read from here.
*/

return [

    'suffixes' => ['Jr', 'Sr', 'I', 'II', 'III', 'IV', 'V'],

    'sexes' => ['Female', 'Male'],

    'civil_statuses' => ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'],

    'education_levels' => [
        'Elementary Level',
        'Elementary Graduate',
        'Highschool Level',
        'Highschool Graduate',
        'College Level',
        'College Graduate',
        'Post Graduate Studies',
    ],

    'districts' => ['Lone', '1st', '2nd', '3rd', '4th', '5th', '6th', 'Others'],

    'municipality_classes' => [
        'First Class', 'Second Class', 'Third Class',
        'Fourth Class', 'Fifth Class', 'Sixth Class',
    ],

    'member_types' => [
        'Farmer',
        'Fisher',
        'Agricultural Extension Worker (AEW)',
        'Student',
        'Others',
    ],

    'specializations' => ['Rice', 'Corn', 'HVCDP', 'Livestock', 'Poultry', 'Fisheries'],

    'hvcdp_commodities' => [
        'Vegetables',
        'Planting Materials',
        'Fruits',
        'Industrial Crops',
        'Alternative Staple',
        'Cut Across',
    ],

    'ati_services' => ['Training', 'Scholarship', 'LSA', 'Internship'],

    'lsa_levels' => ['LSA I', 'LSA II'],

    'lsa_types' => ['Regular LSA', 'RCEF-LSA', 'COCO-LSA'],

    'ati_scholarships' => [
        'Educational Grants for Extension Workers (EdGE)',
        'Educational Assistance for the Youth: Degree Courses in Agriculture and Agricultural Biosystems Engineering (EAsY Agri)',
    ],

    'ati_trainings' => [
        'Training on Leadership',
        'Training on Crop Production',
        'Training on Livestock',
        'Training on Fisheries',
        'Training on Food Processing',
    ],

];