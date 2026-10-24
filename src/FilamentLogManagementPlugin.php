<?php

namespace Alfism1\FilamentLogManagement;

use Alfism1\FilamentLogManagement\Pages\LogViewer;
use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Throwable;
use UnitEnum;

/**
 * Registers the log viewer on a Filament panel.
 *
 *   ->plugin(
 *       FilamentLogManagementPlugin::make()
 *           ->navigationGroup('Developer')
 *           ->navigationSort(4)
 *           ->authorize(fn () => auth()->user()->can('page_LogViewer')),
 *   )
 *
 * Everything is optional: anything left unset falls back to
 * config/log-management.php, so the page also works when it is discovered
 * rather than registered through this plugin.
 */
class FilamentLogManagementPlugin implements Plugin
{
    use EvaluatesClosures;

    public const ID = 'filament-log-management';

    protected string | UnitEnum | Closure | null $navigationGroup = null;

    protected int | Closure | null $navigationSort = null;

    protected string | BackedEnum | Closure | null $navigationIcon = null;

    protected string | Closure | null $navigationLabel = null;

    protected string | Closure | null $slug = null;

    protected bool | Closure | null $registerNavigation = null;

    protected bool | Closure | null $authorize = null;

    /** @var class-string<LogViewer> */
    protected string $pageClass = LogViewer::class;

    public function getId(): string
    {
        return static::ID;
    }

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * The instance registered on the current panel, or a bare instance whose
     * getters all fall through to the config file.
     */
    public static function get(): static
    {
        try {
            $plugin = filament(static::ID);

            if ($plugin instanceof static) {
                return $plugin;
            }
        } catch (Throwable) {
            // No panel in context, or the plugin was never registered.
        }

        return static::make();
    }

    public function register(Panel $panel): void
    {
        $panel->pages([$this->pageClass]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

    public function navigationGroup(string | UnitEnum | Closure | null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationSort(int | Closure | null $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function navigationIcon(string | BackedEnum | Closure | null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function navigationLabel(string | Closure | null $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function slug(string | Closure | null $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function registerNavigation(bool | Closure $condition = true): static
    {
        $this->registerNavigation = $condition;

        return $this;
    }

    /**
     * Gate the page behind a closure or a plain boolean. Overrides the
     * "authorization.permission" config value.
     */
    public function authorize(bool | Closure | null $callback = true): static
    {
        $this->authorize = $callback;

        return $this;
    }

    /**
     * Swap in a subclass of the page, e.g. to add your own header actions.
     *
     * @param  class-string<LogViewer>  $pageClass
     */
    public function usingPage(string $pageClass): static
    {
        $this->pageClass = $pageClass;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolution — plugin value first, config second
    |--------------------------------------------------------------------------
    */

    public function getNavigationGroup(): string | UnitEnum | null
    {
        return $this->evaluate($this->navigationGroup) ?? config('log-management.navigation.group');
    }

    public function getNavigationSort(): ?int
    {
        $sort = $this->evaluate($this->navigationSort) ?? config('log-management.navigation.sort');

        return $sort === null ? null : (int) $sort;
    }

    public function getNavigationIcon(): string | BackedEnum | null
    {
        return $this->evaluate($this->navigationIcon)
            ?? config('log-management.navigation.icon', 'heroicon-o-document-magnifying-glass');
    }

    public function getNavigationLabel(): string
    {
        return $this->evaluate($this->navigationLabel)
            ?? config('log-management.navigation.label')
            ?? __('filament-log-management::log-management.navigation_label');
    }

    public function getSlug(): string
    {
        return $this->evaluate($this->slug)
            ?? config('log-management.navigation.slug', 'log-management');
    }

    public function shouldRegisterNavigation(): bool
    {
        return (bool) ($this->evaluate($this->registerNavigation)
            ?? config('log-management.navigation.register', true));
    }

    public function isAuthorized(): bool
    {
        if ($this->authorize !== null) {
            return (bool) $this->evaluate($this->authorize);
        }

        $permission = config('log-management.authorization.permission');

        if (blank($permission)) {
            return true;
        }

        return auth()->user()?->can($permission) ?? false;
    }

    /**
     * @return class-string<LogViewer>
     */
    public function getPageClass(): string
    {
        return $this->pageClass;
    }
}
