<label class="form-label">Current password<input class="form-control" name="password" type="password" autocomplete="current-password" required></label>
@if($user->admin_mfa_required)
    <label class="form-label">Authenticator or recovery code<input class="form-control" name="factor" autocomplete="off" maxlength="100" required></label>
@endif
@if((! $user->admin_mfa_required || ($chooseMobile ?? false)) && $user->adminRecoveryPhones())
    <label class="form-label">Verify using<select class="form-select" name="recovery_slot">
        @foreach($user->adminRecoveryPhones() as $phoneSlot => $phone)<option value="{{ $phoneSlot }}">Mobile {{ $phoneSlot }} — {{ \App\Support\AdminMfa::maskPhone($phone) }}</option>@endforeach
    </select></label>
@endif
