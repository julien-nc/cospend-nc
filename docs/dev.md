# Development environment

Clone this repository and build:

``` bash
cd /var/www/.../nextcloud/apps
git clone https://github.com/julien-nc/cospend-nc cospend
cd cospend
npm ci
npm run watch
```

Or if you want to use HMR (hot module replacement),
install the [Nextcloud HMR Enabler app](https://github.com/nextcloud/hmr_enabler)
and run this in cospend directory:
``` bash
npm run serve
```

# Public API

Plan was to make Cospend public API strictly identical to [IHateMoney API](https://ihatemoney.readthedocs.io/en/latest/api.html) but there is a restriction i couldn't bypass : the authentication system. IHateMoney uses the basic HTTP authentication, just like Nextcloud user authentication. So, to get a guest access to a Cospend project, this type of authentication was first rejected by Nextcloud user auth system and then accepted by Cospend with a huge latency.

So the only differences between IHateMoney API and Cospend API are :

* The password has to be included in the URL path, just after the project ID, like that : `https://mynextcloud.org/index.php/apps/cospend/api/myproject/projectPassword/bills`
* The parameter `payed_for` cannot be given multiple times like in IHateMoney. It has to be given once with coma separated values.
* A Cospend bill can have several payers, which IHateMoney has no equivalent for. **This API cannot set them**: `apiAddBill` and `apiEditBill` only take the single `payer`. Bills read through it do carry the split, as a `payers` array of `{"id": <memberId>, "amount": <float>}` plus a `payersFallback` boolean. Editing a bill here leaves its split untouched, unless you send a `payer` different from the stored one, which is read as a deliberate change of payer and drops the split. To set a split, use the OCS API (`/ocs/v2.php/apps/cospend/api/v1/projects/{projectId}/bills`), whose bill creation and edition accept an optional `payers` parameter whose amounts must add up to the bill amount.

That's it.

Detailed API description will come later.
