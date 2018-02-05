<?php

namespace App\Console\Commands;

use App\Data;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

class GenerateJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'json:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $today;

    protected $defaultDate;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->today = Carbon::now();
        $this->defaultDate = Carbon::parse('1970-01-01');
    }

    public function handle()
    {
        $fileName = Carbon::now('Asia/Kuala_Lumpur')->format('Ymd_His') . '.json';

        $file = fopen($fileName, 'w');
        fwrite($file, '[');

//        $bar = $this->output->createProgressBar(Data::count());

        $data = new Data;
        $last = $data->orderBy('id', 'desc')->first();


        $data->chunk(1000, function ($chunk) use ($last, $file) {
            foreach ($chunk as $item) {
                $str = $this->generateJsonObject($item);


                if ($last->id != $item->id) {
                    $str .= ',';
                }

                fwrite($file, $str);

                $this->line($item->id);

//                $bar->advance();
            }
        });

        fwrite($file, ']');
        fclose($file);

//        $bar->finish();
    }

    protected function generateJsonObject($model)
    {
        $uuid = Uuid::uuid4();
        $registerDate = $this->getRegisterDate($model->m_date);

        $arr = [
            "UID" => $uuid,
            "created" => $registerDate,
            "profile" => [
                "firstName" => $model->firstname,
                "lastName" => $model->lastname,
                "email" => $this->getEmail($uuid, $model->email),
                "locale" => $this->getLocale($model->m_lang),
                "city" => $model->city,
                "zip" => $model->postcode
            ],

            "data" => [
                "addressLine1" => $this->getFullAddress($model),
                "region" => $model->state,
                "marketCode" => "20503",
                "consumerType" => "PRIVATE",
                "countryCode" => "MY",
                "mobile" => "+" . $model->mobileno,
                "child" => $this->getChild($model),
                "externalApplication" => [
                    [
                        "applicationCode" => "MYNINWEB_MIG",
                        "internalIdentifier" => $model->id
                    ]
                ],
                "initialAppSourceCode" => "MYNINWEB_MIG",
                "gigsys_RGinfantnut" => true
            ],

            "subscriptions" => [
                "MYinfantnut_RGinfantnut" => [
                    "email" => [
                        "isSubscribed" => $this->isSubscribedByOptIn($model->optin),
                        "lastUpdatedSubscriptionState" => $registerDate,
                        "tags" => [
                            "sourceApplication" => "MYNINWEB_MIG"
                        ],
                        "doubleOptIn" => [
                            "status" => "NotConfirmed"
                        ]
                    ]
                ],
                "MYnestlegrp_SBbbyadvml" => [
                    "email" => [
                        "isSubscribed" => $this->isSubscribedByEmail($model->email),
                        "lastUpdatedSubscriptionState" => $registerDate,
                        "tags" => [
                            "sourceApplication" => "MYNINWEB_MIG"
                        ],
                        "doubleOptIn" => [
                            "status" => "NotConfirmed"
                        ]
                    ]
                ],
                "MYinfantnut_SBbbypostal" => [
                    "email" => [
                        "isSubscribed" => $this->isSubscribedByAddress($this->getFullAddress($model)),
                        "lastUpdatedSubscriptionState" => $registerDate,
                        "tags" => [
                            "sourceApplication" => "MYNINWEB_MIG"
                        ],
                        "doubleOptIn" => [
                            "status" => "NotConfirmed"
                        ]
                    ]
                ],
                "MYinfantnut_SBsmspromo" => [
                    "email" => [
                        "isSubscribed" => $this->isSubscribedByMobile($model->mobileno),
                        "lastUpdatedSubscriptionState" => $registerDate,
                        "tags" => [
                            "sourceApplication" => "MYNINWEB_MIG"
                        ],
                        "doubleOptIn" => [
                            "status" => "NotConfirmed"
                        ]
                    ]
                ],
                "loginIDs" => [
                    "emails" => [$this->getEmail($uuid, $model->email)],
                    "password" => [
                        "hash" => $this->generatePassword()
                    ],
                    "hashSettings" => [
                        "algorithm" => "sha1"
                    ],
                    "isVerified" => false,
                    "isActive" => false
                ]
            ]
        ];

        return json_encode($arr);
    }

    protected function getEmail($uuid, $email)
    {
        return $email ?: str_replace('-', '', $uuid) . '@startwellstaywell.com.my';
    }

    protected function getLocale($lang)
    {
        return $lang == 'BM' ? 'ms' : 'en';
    }

    protected function getChild($model)
    {
        $array = [];

        if ($this->validDateOfBirth($model->childdob)) {
            $array[] = [
                "applicationInternalIdentifier" => Uuid::uuid4(),
                "birthDateReliability" => "0",
                "firstName" => $model->childname,
                "birthDate" => $model->childdob->toDateString(),
            ];
        }

        if ($this->validDateOfBirth($model->childdob2)) {
            $array[] = [
                "applicationInternalIdentifier" => Uuid::uuid4(),
                "birthDateReliability" => 0,
                "firstName" => $model->childname2,
                "birthDate" => $model->childdob2->toDateString(),
            ];
        }

        if ($this->validDateOfBirth($model->childdob3)) {
            $array[] = [
                "applicationInternalIdentifier" => Uuid::uuid4(),
                "birthDateReliability" => 0,
                "firstName" => $model->childname3,
                "birthDate" => $model->childdob3->toDateString(),
            ];
        }

        return $array;
    }

    protected function isSubscribedByOptIn($option)
    {
        return $option == 'Yes' ? true : false;
    }

    protected function isSubscribedByEmail($email)
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        return false;
    }

    protected function getFullAddress($model)
    {
        return $model->address1;
    }

    protected function isSubscribedByAddress($fullAddress)
    {
        if (!empty(trim($fullAddress))) {
            return true;
        }

        return false;
    }

    protected function isSubscribedByMobile($mobileno)
    {
        if (!empty($mobileno)) {
            return true;
        }

        return false;
    }

    protected function generatePassword()
    {
        return sha1(str_random());
    }

    protected function validDateOfBirth(Carbon $date)
    {
        return $date->lt($this->today) && $date->diffInYears($this->today) <= 10;
    }

    protected function getRegisterDate(Carbon $date)
    {
        $registerDate = $this->today;
        $invalidDate = Carbon::parse('0000-00-00 00:00:00');

        if ($date->ne($invalidDate)) {
            $registerDate = $date;
        }

        return $registerDate->format("Y-m-d\TH:i:s\Z");
    }
}
