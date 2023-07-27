<?php

class Budget
{
    public const BUDGET = 700;
    public const PAY_DAY = 28;
    public const SATURDAY_BUDGET = 60;
    private int $totalDays;
    private int $countSaturdays;
    private int $budget;
    private int $dailyBudget;
    private int $remainer;

    public function __construct(int $totalDays, int $countSaturdays, ?int $budget = null)
    {
        $this->totalDays = $totalDays;
        $this->countSaturdays = $countSaturdays;
        $this->budget = $budget ?: self::BUDGET;
        $this->calculateBudgetPerDay();
        $this->calculateRemainer();
    }

    public function getDailyBudget() : int {
        return $this->dailyBudget;
    }

    public function getRemainer() : int {
        return $this->remainer;
    }

    private function calculateBudgetPerDay(): void
    {
        $this->dailyBudget = floor (($this->budget - self::SATURDAY_BUDGET * $this->countSaturdays) 
                  / ($this->totalDays - $this->countSaturdays));
    }

    public function calculateRemainer() : void 
    {    
        $remainer = ($this->budget - self::SATURDAY_BUDGET * $this->countSaturdays) 
            - ($this->totalDays - $this->countSaturdays) * $this->dailyBudget;

        $this->remainer = $remainer > 0 ? floor($remainer) : 0;
    }

    public function decrement(\DateTime $day): int 
    {
        $this->budget -= (int)$day->format('N') === 6 ? self::SATURDAY_BUDGET : $this->dailyBudget;
        return $this->budget;
    }

    public static function budgetRemain(int $perDay, int $currentDayNumber, int $countSaturdays): int
    {
        $saturdaysSpent = $countSaturdays * self::SATURDAY_BUDGET;
        $weekDaysSpent = ($currentDayNumber - $countSaturdays) * $perDay;
        
        return self::BUDGET - $weekDaysSpent - $saturdaysSpent;  
    }
}

class PlanningPeriod
{
    public const PAY_DAY = 28;
    private \DateTime $startDate;
    private \DateTime $endDate;
    private int $totalDays;
    private int $countSaturdays;

    public function __construct(\DateTime $start)
    {
        $this->startDate = $this->calculateStartDate($start);
        $this->endDate = (clone($this->startDate))
            ->add(\DateInterval::createFromDateString('1 month'));
        // $currentDayNumber = ($startDate->diff(new \DateTime()))->days;
        $this->totalDays = ($this->startDate->diff($this->endDate))->days;
        $this->countSaturdays = $this->countSaturdays();
    }

    public function getStartDate(): \DateTime
    {
        return $this->startDate;
    }

    public function getTotalDays(): int
    {
        return $this->totalDays;
    }

    public function getCountSaturdays(): int
    {
        return $this->countSaturdays;
    }

    private function calculateStartDate (\DateTime $date)
    {
        $currentDay = (int)(new \DateTime('now', new \DateTimeZone('UTC')))->format('j');

        if ($currentDay < self::PAY_DAY) {
            $date->sub(\DateInterval::createFromDateString('1 month'));
        }
    
        return $date->setDate(
            (int)$date->format('Y'),
            (int)$date->format('m'),
            self::PAY_DAY
        );
    }

    private function countSaturdays(): int
    {
        $start = clone($this->startDate);
        $count = 0;
        
        for ($i = 0; $i < $this->totalDays; $i++) {
            
            $start->modify('+1 day');
            
            if ((int)$start->format('w') === 6) {
                $count += 1;
            }
        }
    
        return $count;
    }
}

class Formatter
{
    private Budget $budget;
    private PlanningPeriod $period;
    private $payDay;

    public function __construct(Budget $budget, PlanningPeriod $period, int $payDay) 
    {
        $this->budget = $budget;
        $this->period = $period;
        $this->payDay = $payDay;
    }

    public function format(): array
    {
        $day = clone($this->period->getStartDate());
        $days = [];

        for ($dayNumber = 1; $dayNumber <= $this->period->getTotalDays(); $dayNumber++) {
            $days[] = $this->assembleDayData($dayNumber, $day);
            $day->modify('+1 day');
        }

        $days = [...$this->prependDays($this->period->getStartDate()), ...$days];

        return [
            'days' => $days,
            'daily_budget' => $this->budget->getDailyBudget(),
            'total_days' => $this->period->getTotalDays(),
            'remainer' => $this->budget->getRemainer(),
        ];
    }

    public function assembleDayData(int $dayNumber, \DateTime $date): array
    {
        $isSaturday = (int)$date->format('N') === 6;
        
        return $this->dayStructure(
            $dayNumber, 
            $this->budget->decrement($date), 
            $isSaturday ? Budget::SATURDAY_BUDGET : $this->budget->getDailyBudget(),
            $date->format('d M'),
            (new DateTime('now'))->format('Y-m-d') === $date->format('Y-m-d'),
            $isSaturday
        );
    }

    private function prependDays(\DateTime $firstDay): array 
    {    
        $day = clone($firstDay);
        $weekDayNumber = (int)$day->format('N');
        $payDay = $this->payDay;
        $prepend = [];

        for ($i = $weekDayNumber; $i >= 0; $i--) {
            $day->modify('-1 day');
            array_unshift(
                $prepend, 
                $this->dayStructure(--$payDay, 0, 0, $day->format('d M'), false, (int)$day->format('N') === 6, false)
            );
        }

        return $prepend;
    }

    private function dayStructure(int $number, int $remains, int $budget, string $date, bool $is_current, bool $is_saturday, bool $is_current_period = true): array
    {
        return compact('number', 'remains', 'budget', 'date', 'is_current', 'is_saturday', 'is_current_period');
    }
}

$period = new PlanningPeriod(new \DateTime('now', new \DateTimeZone('UTC')));
$budget = new Budget($period->getTotalDays(), $period->getCountSaturdays());
$formatter = new Formatter($budget, $period, Budget::PAY_DAY);

header('Content-type: application/json');
echo json_encode($formatter->format(), JSON_PRETTY_PRINT);