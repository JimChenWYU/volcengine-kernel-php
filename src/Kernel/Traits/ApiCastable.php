<?php

namespace Volcengine\Kernel\Traits;

use JsonSerializable;
use Traversable;
use Volcengine\Kernel\Contracts\Arrayable;
use Volcengine\Kernel\DataStructs\BaseResponse;
use Volcengine\Kernel\DataStructs\ErrorInfo;
use Volcengine\Kernel\DataStructs\ResponseMetadata;
use Volcengine\Kernel\Support\Collection;

trait ApiCastable
{
    public function baseResponse($collection)
    {
        $collection = $this->getCollectable($collection);
        return new BaseResponse(
            new ResponseMetadata(
                $collection->get('ResponseMetadata.RequestId'),
                $collection->get('ResponseMetadata.Action'),
                $collection->get('ResponseMetadata.Version'),
                $collection->get('ResponseMetadata.Service'),
                $collection->get('ResponseMetadata.Region'),
                $collection->has('ResponseMetadata.Error') ?
                new ErrorInfo(
                    $collection->get('ResponseMetadata.Error.CodeN'),
                    $collection->get('ResponseMetadata.Error.Code'),
                    $collection->get('ResponseMetadata.Error.Message')
                ) : null
            ),
            $collection->get('Result')
        );
    }

    /**
     * @param mixed $data
     * @return Collection
     */
    private function getCollectable($data): Collection
    {
        if ($data instanceof Collection) {
            return $data;
        }

        if (is_array($data)) {
            return new Collection($data);
        }

        if ($data instanceof Arrayable) {
            return new Collection($data->toArray());
        }

        if ($data instanceof JsonSerializable) {
            return new Collection((array) $data->jsonSerialize());
        }

        if ($data instanceof Traversable) {
            return new Collection(iterator_to_array($data));
        }

        return new Collection((array)$data);
    }
}
