@include('admin.restaurants._form', [
    'restaurant' => $restaurant ?? null,
    'panel' => 'branch',
    'showOwnerLogin' => true,
    'showAdminStatus' => false,
    'cuisineValueField' => 'name',
    'zoneTitle' => 'Branch Delivery Zone',
    'zoneMeta' => "Only restaurants inside this branch's active mapped zone can be saved or approved.",
])
