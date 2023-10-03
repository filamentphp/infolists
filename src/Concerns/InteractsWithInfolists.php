<?php

namespace Filament\Infolists\Concerns;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Component;
use Filament\Infolists\Infolist;
use Filament\Support\Exceptions\Cancel;
use Filament\Support\Exceptions\Halt;

trait InteractsWithInfolists
{
    protected bool $hasInfolistsModalRendered = false;

    /**
     * @var array<string, Infolist>
     */
    protected array $cachedInfolists = [];

    /**
     * @var array<string> | null
     */
    public ?array $mountedInfolistActions = [];

    /**
     * @var array<string, array<string, mixed>> | null
     */
    public ?array $mountedInfolistActionsData = [];

    public ?string $mountedInfolistActionsComponent = null;

    public ?string $mountedInfolistActionsInfolist = null;

    public function getInfolist(string $name): ?Infolist
    {
        $infolist = $this->getCachedInfolists()[$name] ?? null;

        if ($infolist) {
            return $infolist;
        }

        if (! method_exists($this, $name)) {
            return null;
        }

        $infolist = $this->{$name}($this->makeInfolist());

        return $this->cacheInfolist($name, $infolist);
    }

    public function cacheInfolist(string $name, Infolist $infolist): Infolist
    {
        $infolist->name($name);

        return $this->cachedInfolists[$name] = $infolist;
    }

    /**
     * @return array<string, Infolist>
     */
    public function getCachedInfolists(): array
    {
        return $this->cachedInfolists;
    }

    protected function hasCachedInfolist(string $name): bool
    {
        return array_key_exists($name, $this->getCachedInfolists());
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function callMountedInfolistAction(array $arguments = []): mixed
    {
        $action = $this->getMountedInfolistAction();

        if (! $action) {
            return null;
        }

        if ($action->isDisabled()) {
            return null;
        }

        $action->arguments($arguments);

        $form = $this->getMountedInfolistActionForm();

        $result = null;

        try {
            if ($this->mountedInfolistActionHasForm()) {
                $action->callBeforeFormValidated();

                $action->formData($form->getState());

                $action->callAfterFormValidated();
            }

            $action->callBefore();

            $result = $action->call([
                'form' => $form,
            ]);

            $result = $action->callAfter() ?? $result;
        } catch (Halt $exception) {
            return null;
        } catch (Cancel $exception) {
        }

        $action->resetArguments();
        $action->resetFormData();

        if (filled($this->redirectTo)) {
            return $result;
        }

        $this->unmountInfolistAction();

        return $result;
    }

    public function mountInfolistAction(string $name, string $component = null, string $infolist = null): mixed
    {
        $this->mountedInfolistActions[] = $name;
        $this->mountedInfolistActionsData[] = [];

        if (blank($this->mountedInfolistActionsComponent) && filled($component)) {
            $this->mountedInfolistActionsComponent = $component;
        }

        if (blank($this->mountedInfolistActionsInfolist) && filled($infolist)) {
            $this->mountedInfolistActionsInfolist = $infolist;
        }

        $action = $this->getMountedInfolistAction();

        if (! $action) {
            $this->unmountInfolistAction();

            return null;
        }

        if ($action->isDisabled()) {
            $this->unmountInfolistAction();

            return null;
        }

        $this->cacheForm(
            'mountedInfolistActionForm',
            fn () => $this->getMountedInfolistActionForm(),
        );

        try {
            $hasForm = $this->mountedInfolistActionHasForm();

            if ($hasForm) {
                $action->callBeforeFormFilled();
            }

            $action->mount([
                'form' => $this->getMountedInfolistActionForm(),
            ]);

            if ($hasForm) {
                $action->callAfterFormFilled();
            }
        } catch (Halt $exception) {
            return null;
        } catch (Cancel $exception) {
            $this->unmountInfolistAction(shouldCloseParentActions: false);

            return null;
        }

        if (! $this->mountedInfolistActionShouldOpenModal()) {
            return $this->callMountedInfolistAction();
        }

        $this->resetErrorBag();

        $this->dispatchBrowserEvent('open-modal', [
            'id' => "{$this->id}-infolist-action",
        ]);

        return null;
    }

    public function mountedInfolistActionShouldOpenModal(): bool
    {
        $action = $this->getMountedInfolistAction();

        if ($action->isModalHidden()) {
            return false;
        }

        return $action->getModalDescription() ||
            $action->getModalContent() ||
            $action->getModalContentFooter() ||
            $action->getInfolist() ||
            $this->mountedInfolistActionHasForm();
    }

    public function mountedInfolistActionHasForm(): bool
    {
        return (bool) count($this->getMountedInfolistActionForm()?->getComponents() ?? []);
    }

    public function getMountedInfolistAction(): ?Action
    {
        if (! count($this->mountedInfolistActions ?? [])) {
            return null;
        }

        return $this->getMountedInfolistActionComponent()?->getAction($this->mountedInfolistActions);
    }

    public function getMountedInfolistActionComponent(): ?Component
    {
        $infolist = $this->getInfolist($this->mountedInfolistActionsInfolist);

        if (! $infolist) {
            return null;
        }

        return $infolist->getComponent($this->mountedInfolistActionsComponent);
    }

    public function getMountedInfolistActionForm(): ?Form
    {
        $action = $this->getMountedInfolistAction();

        if (! $action) {
            return null;
        }

        if ((! $this->isCachingForms) && $this->hasCachedForm('mountedInfolistActionForm')) {
            return $this->getForm('mountedInfolistActionForm');
        }

        return $action->getForm(
            $this->makeForm()
                ->model($action->getRecord())
                ->statePath('mountedInfolistActionsData.' . array_key_last($this->mountedInfolistActionsData))
                ->operation(implode('.', $this->mountedInfolistActions)),
        );
    }

    public function unmountInfolistAction(bool $shouldCloseParentActions = true): void
    {
        $action = $this->getMountedInfolistAction();

        if (! ($shouldCloseParentActions && $action)) {
            array_pop($this->mountedInfolistActions);
            array_pop($this->mountedInfolistActionsData);
        } elseif ($action->shouldCloseAllParentActions()) {
            $this->mountedInfolistActions = [];
            $this->mountedInfolistActionsData = [];
        } else {
            $parentActionToCloseTo = $action->getParentActionToCloseTo();

            while (true) {
                $recentlyClosedParentAction = array_pop($this->mountedInfolistActions);
                array_pop($this->mountedInfolistActionsData);

                if (
                    blank($parentActionToCloseTo) ||
                    ($recentlyClosedParentAction === $parentActionToCloseTo)
                ) {
                    break;
                }
            }
        }

        if (! count($this->mountedInfolistActions)) {
            $this->mountedInfolistActionsComponent = null;
            $this->mountedInfolistActionsInfolist = null;

            $this->dispatchBrowserEvent('close-modal', [
                'id' => "{$this->id}-infolist-action",
            ]);

            return;
        }

        $this->cacheForm(
            'mountedInfolistActionForm',
            fn () => $this->getMountedInfolistActionForm(),
        );

        $this->resetErrorBag();

        $this->dispatchBrowserEvent('open-modal', [
            'id' => "{$this->id}-infolist-action",
        ]);
    }

    protected function makeInfolist(): Infolist
    {
        return Infolist::make($this);
    }

    /**
     * @return array<string, Forms\Form>
     */
    protected function getInteractsWithInfolistsForms(): array
    {
        return [
            'mountedInfolistActionForm' => $this->getMountedInfolistActionForm(),
        ];
    }
}
