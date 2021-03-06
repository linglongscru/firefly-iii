<?php
/**
 * BudgetControllerTest.php
 * Copyright (c) 2017 thegrumpydictator@gmail.com
 * This software may be modified and distributed under the terms of the Creative Commons Attribution-ShareAlike 4.0 International License.
 *
 * See the LICENSE file for details.
 */

declare(strict_types=1);

namespace Tests\Feature\Controllers\Chart;


use Carbon\Carbon;
use FireflyIII\Generator\Chart\Basic\GeneratorInterface;
use FireflyIII\Helpers\Collector\JournalCollectorInterface;
use FireflyIII\Models\Budget;
use FireflyIII\Models\BudgetLimit;
use FireflyIII\Models\Transaction;
use FireflyIII\Models\TransactionType;
use FireflyIII\Repositories\Budget\BudgetRepositoryInterface;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Class BudgetControllerTest
 *
 * @package Tests\Feature\Controllers\Chart
 */
class BudgetControllerTest extends TestCase
{

    /**
     * @covers       \FireflyIII\Http\Controllers\Chart\BudgetController::budget
     * @covers       \FireflyIII\Http\Controllers\Chart\BudgetController::__construct
     * @dataProvider dateRangeProvider
     *
     * @param string $range
     */
    public function testBudget(string $range)
    {
        $repository = $this->mock(BudgetRepositoryInterface::class);
        $generator  = $this->mock(GeneratorInterface::class);

        $repository->shouldReceive('firstUseDate')->andReturn(new Carbon('2015-01-01'))->once();
        $repository->shouldReceive('spentInPeriod')->andReturn('-100');
        $generator->shouldReceive('singleSet')->andReturn([])->once();


        $this->be($this->user());
        $this->changeDateRange($this->user(), $range);
        $response = $this->get(route('chart.budget.budget', [1]));
        $response->assertStatus(200);
    }

    /**
     * @covers       \FireflyIII\Http\Controllers\Chart\BudgetController::budgetLimit
     * @dataProvider dateRangeProvider
     *
     * @param string $range
     */
    public function testBudgetLimit(string $range)
    {
        $repository = $this->mock(BudgetRepositoryInterface::class);
        $generator  = $this->mock(GeneratorInterface::class);


        $repository->shouldReceive('spentInPeriod')->andReturn('-100');
        $generator->shouldReceive('singleSet')->once()->andReturn([]);

        $this->be($this->user());
        $this->changeDateRange($this->user(), $range);
        $response = $this->get(route('chart.budget.budget-limit', [1, 1]));
        $response->assertStatus(200);
    }

    /**
     * @covers                   \FireflyIII\Http\Controllers\Chart\BudgetController::budgetLimit
     * @expectedExceptionMessage This budget limit is not part of this budget.
     */
    public function testBudgetLimitWrongLimit()
    {
        $repository = $this->mock(BudgetRepositoryInterface::class);
        $generator  = $this->mock(GeneratorInterface::class);

        $this->be($this->user());
        $response = $this->get(route('chart.budget.budget-limit', [1, 8]));
        $response->assertStatus(500);
    }

    /**
     * @covers       \FireflyIII\Http\Controllers\Chart\BudgetController::frontpage
     * @covers       \FireflyIII\Http\Controllers\Chart\BudgetController::getExpensesForBudget
     * @covers       \FireflyIII\Http\Controllers\Chart\BudgetController::spentInPeriodWithout
     * @covers       \FireflyIII\Http\Controllers\Chart\BudgetController::spentInPeriodMulti
     * @dataProvider dateRangeProvider
     *
     * @param string $range
     */
    public function testFrontpage(string $range)
    {
        $repository             = $this->mock(BudgetRepositoryInterface::class);
        $generator              = $this->mock(GeneratorInterface::class);
        $collector              = $this->mock(JournalCollectorInterface::class);
        $budget                 = factory(Budget::class)->make();
        $budgetLimit            = factory(BudgetLimit::class)->make();
        $budgetLimit->budget_id = $budget->id;
        $transaction            = factory(Transaction::class)->make();


        $repository->shouldReceive('getActiveBudgets')->andReturn(new Collection([$budget]))->once();
        $repository->shouldReceive('getBudgetLimits')->once()->andReturn(new Collection([$budgetLimit]));
        $repository->shouldReceive('spentInPeriod')->andReturn('-100');

        $collector->shouldReceive('setAllAssetAccounts')->andReturnSelf()->once();
        $collector->shouldReceive('setTypes')->withArgs([[TransactionType::WITHDRAWAL]])->andReturnSelf()->once();
        $collector->shouldReceive('setRange')->andReturnSelf()->once();
        $collector->shouldReceive('withoutBudget')->andReturnSelf()->once();
        $collector->shouldReceive('getJournals')->andReturn(new Collection([$transaction]))->once();

        $generator->shouldReceive('multiSet')->once()->andReturn([]);

        $this->be($this->user());
        $this->changeDateRange($this->user(), $range);
        $response = $this->get(route('chart.budget.frontpage'));
        $response->assertStatus(200);
    }

    /**
     * @covers       \FireflyIII\Http\Controllers\Chart\BudgetController::frontpage
     * @covers       \FireflyIII\Http\Controllers\Chart\BudgetController::getExpensesForBudget
     * @covers       \FireflyIII\Http\Controllers\Chart\BudgetController::spentInPeriodWithout
     * @covers       \FireflyIII\Http\Controllers\Chart\BudgetController::spentInPeriodMulti
     * @dataProvider dateRangeProvider
     *
     * @param string $range
     */
    public function testFrontpageMultiLimit(string $range)
    {
        $repository     = $this->mock(BudgetRepositoryInterface::class);
        $generator      = $this->mock(GeneratorInterface::class);
        $collector      = $this->mock(JournalCollectorInterface::class);
        $budget         = factory(Budget::class)->make();
        $one            = factory(BudgetLimit::class)->make();
        $two            = factory(BudgetLimit::class)->make();
        $one->budget_id = $budget->id;
        $two->budget_id = $budget->id;
        $transaction    = factory(Transaction::class)->make();


        $repository->shouldReceive('getActiveBudgets')->andReturn(new Collection([$budget]))->once();
        $repository->shouldReceive('getBudgetLimits')->once()->andReturn(new Collection([$one, $two]));
        $repository->shouldReceive('spentInPeriod')->andReturn('-100');

        $collector->shouldReceive('setAllAssetAccounts')->andReturnSelf()->once();
        $collector->shouldReceive('setTypes')->withArgs([[TransactionType::WITHDRAWAL]])->andReturnSelf()->once();
        $collector->shouldReceive('setRange')->andReturnSelf()->once();
        $collector->shouldReceive('withoutBudget')->andReturnSelf()->once();
        $collector->shouldReceive('getJournals')->andReturn(new Collection([$transaction]))->once();

        $generator->shouldReceive('multiSet')->once()->andReturn([]);

        $this->be($this->user());
        $this->changeDateRange($this->user(), $range);
        $response = $this->get(route('chart.budget.frontpage'));
        $response->assertStatus(200);
    }

    /**
     * @covers       \FireflyIII\Http\Controllers\Chart\BudgetController::frontpage
     * @covers       \FireflyIII\Http\Controllers\Chart\BudgetController::getExpensesForBudget
     * @covers       \FireflyIII\Http\Controllers\Chart\BudgetController::spentInPeriodWithout
     * @covers       \FireflyIII\Http\Controllers\Chart\BudgetController::spentInPeriodMulti
     * @dataProvider dateRangeProvider
     *
     * @param string $range
     */
    public function testFrontpageNoLimits(string $range)
    {
        $repository  = $this->mock(BudgetRepositoryInterface::class);
        $generator   = $this->mock(GeneratorInterface::class);
        $collector   = $this->mock(JournalCollectorInterface::class);
        $budget      = factory(Budget::class)->make();
        $transaction = factory(Transaction::class)->make();


        $repository->shouldReceive('getActiveBudgets')->andReturn(new Collection([$budget]));
        $repository->shouldReceive('getBudgetLimits')->once()->andReturn(new Collection);
        $repository->shouldReceive('spentInPeriod')->andReturn('-100');

        $collector->shouldReceive('setAllAssetAccounts')->andReturnSelf()->once();
        $collector->shouldReceive('setTypes')->withArgs([[TransactionType::WITHDRAWAL]])->andReturnSelf()->once();
        $collector->shouldReceive('setRange')->andReturnSelf()->once();
        $collector->shouldReceive('withoutBudget')->andReturnSelf()->once();
        $collector->shouldReceive('getJournals')->andReturn(new Collection([$transaction]))->once();

        $generator->shouldReceive('multiSet')->once()->andReturn([]);

        $this->be($this->user());
        $this->changeDateRange($this->user(), $range);
        $response = $this->get(route('chart.budget.frontpage'));
        $response->assertStatus(200);
    }

    /**
     * @covers \FireflyIII\Http\Controllers\Chart\BudgetController::period
     * @covers \FireflyIII\Http\Controllers\Chart\BudgetController::getBudgetedInPeriod
     */
    public function testPeriod()
    {
        $repository             = $this->mock(BudgetRepositoryInterface::class);
        $generator              = $this->mock(GeneratorInterface::class);
        $budget                 = factory(Budget::class)->make();
        $budgetLimit            = factory(BudgetLimit::class)->make();
        $budgetLimit->budget_id = $budget->id;

        $repository->shouldReceive('getBudgetPeriodReport')->andReturn([])->once();
        $repository->shouldReceive('getBudgetLimits')->andReturn(new Collection([$budgetLimit]));
        $generator->shouldReceive('multiSet')->once()->andReturn([]);


        $this->be($this->user());
        $response = $this->get(route('chart.budget.period', [1, '1', '20120101', '20120131']));
        $response->assertStatus(200);
    }

    /**
     * @covers \FireflyIII\Http\Controllers\Chart\BudgetController::periodNoBudget
     */
    public function testPeriodNoBudget()
    {
        $repository = $this->mock(BudgetRepositoryInterface::class);
        $generator  = $this->mock(GeneratorInterface::class);

        $repository->shouldReceive('getNoBudgetPeriodReport')->andReturn([])->once();
        $generator->shouldReceive('singleSet')->once()->andReturn([]);

        $this->be($this->user());
        $response = $this->get(route('chart.budget.period.no-budget', ['1', '20120101', '20120131']));
        $response->assertStatus(200);
    }

}
