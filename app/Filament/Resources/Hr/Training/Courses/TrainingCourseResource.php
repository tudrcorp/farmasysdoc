<?php

namespace App\Filament\Resources\Hr\Training\Courses;

use App\Filament\Clusters\Training\TrainingCluster;
use App\Filament\Resources\Hr\Training\Courses\Pages\CreateTrainingCourse;
use App\Filament\Resources\Hr\Training\Courses\Pages\EditTrainingCourse;
use App\Filament\Resources\Hr\Training\Courses\Pages\ListTrainingCourses;
use App\Filament\Resources\Hr\Training\Courses\Schemas\TrainingCourseForm;
use App\Filament\Resources\Hr\Training\Courses\Tables\TrainingCoursesTable;
use App\Models\TrainingCourse;
use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TrainingCourseResource extends Resource
{
    protected static ?string $model = TrainingCourse::class;

    protected static ?string $cluster = TrainingCluster::class;

    protected static ?string $slug = 'catalogo';

    protected static ?string $navigationLabel = 'Catálogo';

    protected static ?string $modelLabel = 'curso';

    protected static ?string $pluralModelLabel = 'cursos';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BookOpen;

    public static function form(Schema $schema): Schema
    {
        return TrainingCourseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrainingCoursesTable::configure($table);
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        $user = request()->user() ?? Auth::user();

        return $user instanceof User
            && ! FarmaadminDeliveryUserAccess::denies(static::class)
            && $user->canManageTraining();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && $record instanceof TrainingCourse && ! $record->sessions()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrainingCourses::route('/'),
            'create' => CreateTrainingCourse::route('/create'),
            'edit' => EditTrainingCourse::route('/{record}/edit'),
        ];
    }
}
