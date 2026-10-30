@php
    $appointmentsActive = request()->routeIs('me.appointments');
    $profileActive = request()->routeIs('profile.*');
@endphp

<nav class="bottom-nav client-bottom-nav fixed bottom-0 left-0 right-0 z-50" aria-label="Навигация клиента">
    <div class="client-bottom-nav__items">
        <a href="{{ route('me.appointments') }}"
           class="nav-item {{ $appointmentsActive ? 'active' : '' }}"
           aria-describedby="client-appointments-hint"
           @if($appointmentsActive) aria-current="page" @endif>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true" focusable="false">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>Мои записи</span>
        </a>
        <a href="{{ route('profile.edit') }}"
           class="nav-item {{ $profileActive ? 'active' : '' }}"
           @if($profileActive) aria-current="page" @endif>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true" focusable="false">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span>Профиль</span>
        </a>
    </div>
    <span id="client-appointments-hint" class="sr-only">История и предстоящие</span>
</nav>
