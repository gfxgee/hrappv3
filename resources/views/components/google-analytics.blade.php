<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-CHWWG9H8R8"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  @auth
    {{-- Pseudonymous per-user tracking: send the numeric user id (never name/email,
         which GA prohibits). Map the id back to a person internally if needed. --}}
    gtag('config', 'G-CHWWG9H8R8', {
      user_id: @js((string) auth()->id()),
    });
    gtag('set', 'user_properties', {
      department: @js(auth()->user()->department?->name),
      role: @js(auth()->user()->getRoleNames()->first()),
    });
  @else
    gtag('config', 'G-CHWWG9H8R8');
  @endauth
</script>
