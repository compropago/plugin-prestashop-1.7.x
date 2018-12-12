/**
 * Created by Arthur on 10/11/16.
 */

function setBack(types)
{
	const assets_url = 'https://s3.amazonaws.com/compropago/assets/images/receipt/';
	var
		dropShops = document.querySelector("select.providers_list"),
		back = '';

	switch (types)
	{
		case 'OXXO':
			back = `${assets_url}receipt-oxxo-medium.png`;
			break;
		case 'SEVEN_ELEVEN':
			back = `${assets_url}receipt-seven-medium.png`;
			break;
		case 'COPPEL':
			back = `${assets_url}receipt-coppel-medium.png`;
			break;
		case 'chedraui':
			back = `${assets_url}receipt-chedraui-medium.png`;
			break;
		case 'EXTRA':
			back = `${assets_url}receipt-extra-medium.png`;
			break;
		case 'FARMACIA_ESQUIVAR':
			back = `${assets_url}receipt-esquivar-medium.png`;
			break;
		//case 'farmacia_benavides':
		//    back = `${assets_url}receipt-benavides-medium.png`;
		//    break;
		case 'ELEKTRA':
			back = `${assets_url}receipt-elektra-medium.png`;
			break;
		case 'CASA_LEY':
			back = `${assets_url}receipt-ley-medium.png`;
			break;
		case 'PITICO':
			back = `${assets_url}receipt-pitico-medium.png`;
			break;
		case 'TELECOMM':
			back = `${assets_url}receipt-telecomm-medium.png`;
			break;
		case 'FARMACIA_ABC':
			back = `${assets_url}receipt-abc-medium.png`;
			break;
	}

	dropShops.style.backgroundImage = `url('${back}')`;
}

function cleanSelections()
{
	var dropShops = document.querySelectorAll("ul.providers_list label");
	for(x = 0; x < dropShops.length; x++)
	{
		dropShops[x].classList.remove('provider_selected');
	}
}

window.onload = function()
{
	var selectProviders = document.querySelector("select.providers_list");

	if (selectProviders)
	{
		setBack(selectProviders.value);

		selectProviders.addEventListener('change', function(evt)
		{
			elem = evt.target;
			setBack(elem.value);
		});
	}
	else
	{
		var listProviders = document.querySelectorAll("ul.providers_list label");

		for(x = 0; x < listProviders.length; x++)
		{
			listProviders[x].addEventListener('click', function()
			{
				cleanSelections();
				this.classList.add('provider_selected');
			});
		}
	}

	// Webhook input
	$('#COMPROPAGO_WEBHOOK_SHOW').propo('readonly', true);
	$('#COMPROPAGO_WEBHOOK_SHOW').propo('disabled', true);
};
