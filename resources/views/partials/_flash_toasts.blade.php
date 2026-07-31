{{--
    Session-flash toast container. Rendered near the top of the body by
    both authenticated (layouts/app.blade.php) and public
    (layouts/public.blade.php) layouts.

    Each flash session key maps to a toast variant. Validation errors
    render as a single danger toast with an unordered list so long
    error runs stay readable. Auto-dismiss + close-button behaviour
    is wired up client-side in resources/js/toast.js.
--}}
<div class="toast-container" aria-live="polite" aria-atomic="false">
    @if(session('status'))
        <x-toast variant="success">{{ session('status') }}</x-toast>
    @endif
    @if(session('success'))
        <x-toast variant="success">{{ session('success') }}</x-toast>
    @endif
    @if(session('info'))
        <x-toast variant="info">{{ session('info') }}</x-toast>
    @endif
    @if(session('warning'))
        <x-toast variant="warning">{{ session('warning') }}</x-toast>
    @endif
    @if(session('error'))
        <x-toast variant="danger">{{ session('error') }}</x-toast>
    @endif
    @if($errors->any())
        <x-toast variant="danger" title="Please fix the following:">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-toast>
    @endif
</div>
