<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            ['P300', 'Proposal/Tender Preparation'],
            ['P21008', 'PMT- The Legacy Business Centre'],
            ['P21008-002', 'MEP & Structural Design, The Legacy Business Centre'],
            ['P23161', 'Detailed Engineering - Miscellaneous Works at TPO for ADNOC Onshore (RSME)'],
            ['P24225', 'Buhasa TSF Construction Office - ADNOC Onshore / NISCO General Contracting'],
            ['P23208', 'Detailed Engineering_Debottlenecking of Facilities at Gas Cluster and Wet Air Receiver ARMEPCO and ADNOC OFFSHORE'],
            ['P24236', 'Detailed Engineering of LP/LLP Steam Modification RSME and Al Hosn'],
            ['P24226', 'Detailed Engineering and Procurement Services of the EPCM Implementation Scope of Work BU Hasa (TSF) Temporary Site Facility ADNOC Onshore/Marks Projects Management'],
            ['P24247', 'On-Call Agreement (2 years Contract) Detailed Tie-in Engineering of Bu Hasa Tie-in Package 2, ADNOC Onshore/ Robt Stone Middle East Ltd.'],
            ['P24250', 'Detailed Engineering and Procurement Services for the EPC of Modification of Well Flowline Put Well On Production (On Call-Off Basis).'],
            ['P24265', 'Detailed Engineering and Procurement Services for the EPCM of West East (WEP) Project, Jebel Dhanna Temporary Site Facility, ADNOC Onshore/Marks Projects Management (MPM)'],
            ['P24306', 'Project Management Consultancy (PMC) Services/Engineering Technical Support to MPM Projects, ADNOC Onshore/Marks Projects Management (MPM)'],
            ['P24302', 'Detailed Engineering and Procurement Services for the EPCM Project Wave C3B-IBC (BAB), BAB Industrial Buildings, Substation, MISC Utility Works, Fire Station & Associated Works - Construction Package - 04 (CWP-04)'],
            ['P24318', 'Detailed Engineering and Procurement Services for the EPCM Implementation Work for ASAB Construction Office & Temporary Site Facility (TSF) ADNOC Onshore, Abu Dhabi, United Arab Emirates.'],
            ['P24315', 'Detailed Engineering and Procurement Services for the EPC Works in Jebel Dhanna CICPA, Traffic Management and other Associated Works for ADNOC Onshore, Abu Dhabi, United Arab Emirates'],
            ['P25350', 'Engineering (As-Built) and 3D Modelling Services for the EPC Scope of Work for P152 - NEB Miscellaneous Modifications & Enhancement Project, ADNOC Onshore, Abu Dhabi, UAE'],
            ['P25356', 'Detailed Engineering of EPC Scope of Work for Additional Wellhead Injection Wells (DY-368,DY-372, and DY-60) ADNOC Onshore Facilities, ADNOC Onshore, Abu Dhabi, UAE'],
            ['P25371', 'Engineering Verification/Adequacy Checking for Pipe Support & Sleeper Modifications, ADNOC Gas Processing, Abu Dhabi, UAE'],
            ['P24316', 'Engineering, Procurement And Construction EPC Scope Of Work For Package - 5, Sarb Produced Water Treatment Plant Project At Zirku Island Adnoc Offshore'],
            ['P25406', '2D Update / As Built of Piping GAD Drawing'],
            ['P25440', 'EPC For Balance Of Plant (BOP) Works For Habshan P5 Short Term Facilities Project'],
            ['P25456', 'Construction of Central Warehouse and Temporary Storage & Handling Facility'],
            ['P26483', 'Lighting and Small Power for Central Utility Plant and Electrical Special Facilities in Ruwais, UAE'],
            ['P26493', 'ASAB Construction Office And Temporary Site Facility (TSF) And Associated Works'],
        ];

        $codes = array_column($projects, 0);

        Project::whereNotIn('project_code', $codes)->delete();

        foreach ($projects as [$code, $name]) {
            Project::updateOrCreate(
                ['project_code' => $code],
                ['project_name' => $name, 'client_name' => 'ADNOC', 'is_active' => true]
            );
        }
    }
}
