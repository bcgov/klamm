<?php

use App\Models\User;
use App\Models\BusinessArea;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->createRoles();
});

// describe('User Business Area Relationship', function () {
//     test('user can have business areas relationship', function () {
//         $user = User::factory()->create();

//         expect($user->businessAreas())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
//     });

//     test('user can be attached to business areas', function () {
//         $user = User::factory()->create();

//         if (class_exists(BusinessArea::class)) {
//             $businessArea = BusinessArea::factory()->create();
//             $user->businessAreas()->attach($businessArea);

//             expect($user->businessAreas)->toHaveCount(1)
//                 ->and($user->businessAreas->first()->id)->toBe($businessArea->id);
//         } else {
//             $this->markTestSkipped('BusinessArea model not found');
//         }
//     });

//     test('user can have multiple business areas', function () {
//         $user = User::factory()->create();

//         if (class_exists(BusinessArea::class)) {
//             $businessArea1 = BusinessArea::factory()->create(['name' => 'Area 1']);
//             $businessArea2 = BusinessArea::factory()->create(['name' => 'Area 2']);

//             $user->businessAreas()->attach([$businessArea1->id, $businessArea2->id]);

//             expect($user->businessAreas)->toHaveCount(2);
//         } else {
//             $this->markTestSkipped('BusinessArea model not found');
//         }
//     });

//     test('user can be detached from business areas', function () {
//         $user = User::factory()->create();

//         if (class_exists(BusinessArea::class)) {
//             $businessArea = BusinessArea::factory()->create();
//             $user->businessAreas()->attach($businessArea);

//             expect($user->businessAreas)->toHaveCount(1);

//             $user->businessAreas()->detach($businessArea);
//             $user->refresh();

//             expect($user->businessAreas)->toHaveCount(0);
//         } else {
//             $this->markTestSkipped('BusinessArea model not found');
//         }
//     });
// });

// describe('User Activity Relationship', function () {
//     test('user can have activities relationship', function () {
//         $user = User::factory()->create();

//         expect($user->activities())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
//     });

//     test('user can have activities logged', function () {
//         $user = User::factory()->create();

//         activity()
//             ->causedBy($user)
//             ->log('User performed an action');

//         expect($user->activities)->toHaveCount(1)
//             ->and($user->activities->first()->description)->toBe('User performed an action')
//             ->and($user->activities->first()->causer_id)->toBe($user->id);
//     });

//     test('user can have multiple activities', function () {
//         $user = User::factory()->create();

//         activity()->causedBy($user)->log('First action');
//         activity()->causedBy($user)->log('Second action');
//         activity()->causedBy($user)->log('Third action');

//         expect($user->activities)->toHaveCount(3);
//     });

//     test('user activities are properly attributed', function () {
//         $user1 = User::factory()->create(['name' => 'User One']);
//         $user2 = User::factory()->create(['name' => 'User Two']);

//         activity()->causedBy($user1)->log('User 1 action');
//         activity()->causedBy($user2)->log('User 2 action');

//         expect($user1->activities)->toHaveCount(1)
//             ->and($user2->activities)->toHaveCount(1)
//             ->and($user1->activities->first()->description)->toBe('User 1 action')
//             ->and($user2->activities->first()->description)->toBe('User 2 action');
//     });

//     test('user activities include proper metadata', function () {
//         $user = User::factory()->create();

//         activity()
//             ->causedBy($user)
//             ->withProperties(['key' => 'value'])
//             ->log('Action with metadata');

//         $activity = $user->activities->first();

//         expect($activity->properties)->toHaveKey('key')
//             ->and($activity->properties['key'])->toBe('value')
//             ->and($activity->causer_type)->toBe(User::class);
//     });
// });

// describe('User Role and Activity Integration', function () {
//     test('user role changes can be logged as activities', function () {
//         $user = User::factory()->create();

//         activity()
//             ->causedBy($user)
//             ->withProperties(['role' => 'admin'])
//             ->log('Role assigned');

//         $user->assignRole('admin');

//         expect($user->hasRole('admin'))->toBeTrue()
//             ->and($user->activities)->toHaveCount(1)
//             ->and($user->activities->first()->properties['role'])->toBe('admin');
//     });

//     test('user business area assignments can be logged', function () {
//         $user = User::factory()->create();

//         if (class_exists(BusinessArea::class)) {
//             $businessArea = BusinessArea::factory()->create(['name' => 'Test Area']);

//             activity()
//                 ->causedBy($user)
//                 ->withProperties(['business_area' => $businessArea->name])
//                 ->log('Business area assigned');

//             $user->businessAreas()->attach($businessArea);

//             expect($user->businessAreas)->toHaveCount(1)
//                 ->and($user->activities)->toHaveCount(1);
//         } else {
//             $this->markTestSkipped('BusinessArea model not found');
//         }
//     });
// });

// describe('User Cleanup and Integrity', function () {
//     test('user deletion handles relationships properly', function () {
//         $user = User::factory()->create();
//         $user->assignRole('admin');

//         activity()->causedBy($user)->log('Test activity');

//         if (class_exists(BusinessArea::class)) {
//             $businessArea = BusinessArea::factory()->create();
//             $user->businessAreas()->attach($businessArea);
//         }

//         $userId = $user->id;

//         $activityCount = Activity::where('causer_id', $userId)->count();
//         expect($activityCount)->toBeGreaterThan(0);

//         $user->delete();

//         expect(User::find($userId))->toBeNull();

//         $remainingActivities = Activity::where('causer_id', $userId)->count();

//         expect($remainingActivities)->toBeGreaterThanOrEqual(0);
//     });

//     test('user relationships can be queried efficiently', function () {
//         $user = User::factory()->create();

//         $userWithRelations = User::with(['businessAreas', 'activities'])->find($user->id);

//         expect($userWithRelations)->not->toBeNull()
//             ->and($userWithRelations->relationLoaded('businessAreas'))->toBeTrue()
//             ->and($userWithRelations->relationLoaded('activities'))->toBeTrue();
//     });
// });
